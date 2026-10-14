<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Email module
 *
 * Ported from Kohana 2.2.3 Core to Kohana 3.0 module
 *
 * Updated to use Swiftmailer 4.0.4 (or later, as Composer handles it)
 *
 * @package    Core
 * @author     Kohana Team
 * @copyright  (c) 2007-2008 Kohana Team
 * @license    http://kohanaphp.com/license.html
 */
class Email {

    // SwiftMailer instance
    protected static $mail;

    /**
     * Creates a SwiftMailer instance.
     *
     * @param   array|null  $config  Configuration array, or null to load from config/email.php
     * @return  Swift_Mailer  Swift object
     * @throws  Kohana_Exception
     */
    public static function connect($config = NULL)
    {
        // SwiftMailer is loaded via Composer's autoloader, no need for require
        // if ( ! class_exists('Swift_Mailer', FALSE))
        // {
        //     require Kohana::find_file('vendor', 'swift/swift_required');
        // }

        // Load default configuration from config/email.php
        if ($config === NULL)
        {
			$config = Kohana::$config->load('email');
			
        }
		
		// var_dump ($config);

        //if (empty($config) || !is_array($config))
		if (empty($config))
        {
            throw new Kohana_Exception('Email configuration not found or invalid.');
        }

        $transport = null;

        switch ($config['driver'])
        {
            case 'smtp':
                // Set port
                $port = empty($config['options']['port']) ? 25 : (int) $config['options']['port'];

                // Create SMTP Transport
                $transport = new Swift_SmtpTransport($config['options']['hostname'], $port);

                if ( ! empty($config['options']['encryption']))
                {
                    // Set encryption
                    $transport->setEncryption($config['options']['encryption']);
                }

                // Do authentication, if part of the DSN
                if ( ! empty($config['options']['username']))
                {
                    $transport->setUsername($config['options']['username']);
                }
                if ( ! empty($config['options']['password']))
                {
                    $transport->setPassword($config['options']['password']);
                }

                // Set the timeout
                $transport->setTimeout(empty($config['options']['timeout']) ? 5 : (int) $config['options']['timeout']);
            break;

            case 'sendmail':
                // Create a sendmail connection
                $command = empty($config['options']['command']) ? "/usr/sbin/sendmail -bs" : $config['options']['command'];
                $transport = new Swift_SendmailTransport($command);
            break;

            case 'mail':
                // Use the native connection
                $transport = new Swift_MailTransport();
            break;

            default:
                throw new Kohana_Exception('Unsupported email driver: :driver', array(':driver' => $config['driver']));
        }

        // Create the SwiftMailer instance
        return Email::$mail = new Swift_Mailer($transport);
    }

    /**
     * Send an email message.
     *
     * @param   string|array  $to          recipient email (and name), or an array of To, Cc, Bcc names
     * @param   string|array  $from        sender email (and name)
     * @param   string        $subject     message subject
     * @param   string        $message_body message body
     * @param   boolean       $html        send email as HTML
     * @param   string|array  $attachment  path to a single attachment or an array of paths/path-name pairs
     * @return  integer       number of emails sent
     */
    public static function send($to, $from, $subject, $message_body, $html = FALSE, $attachment = FALSE)
    {
        // Connect to SwiftMailer if not already connected
        if (Email::$mail === NULL)
        {
            Email::connect();
        }

        // Determine the message type
        $content_type = ($html === TRUE) ? 'text/html' : 'text/plain';

        // Create the message
        $message = new Swift_Message($subject, $message_body, $content_type, 'utf-8');

        // Handle attachments
        if ($attachment)
        {
            if (is_string($attachment))
            {
                // Single file attachment
                $file_path = DOCROOT . ltrim($attachment, '/');
                if (file_exists($file_path))
                {
                    $message->attach(Swift_Attachment::fromPath($file_path));
                }
                else
                {
                    Kohana::$log->add(Log::ERROR, 'Email attachment file not found: :file', array(':file' => $file_path));
                }
            }
            elseif (is_array($attachment))
            {
                foreach ($attachment as $file_key => $file_value)
                {
                    $file_path = '';
                    $file_name = '';

                    if (is_int($file_key))
                    {
                        // Array of paths: array('/path/to/file.txt', '/path/to/another.pdf')
                        $file_path = DOCROOT . ltrim($file_value, '/');
                        $file_name = basename($file_value);
                    }
                    else
                    {
                        // Array of path => name: array('/path/to/file.txt' => 'MyDocument.txt')
                        $file_path = DOCROOT . ltrim($file_key, '/');
                        $file_name = $file_value;
                    }

                    if (file_exists($file_path))
                    {
                        $message->attach(Swift_Attachment::fromPath($file_path)->setFilename($file_name));
                    }
                    else
                    {
                        Kohana::$log->add(Log::ERROR, 'Email attachment file not found: :file', array(':file' => $file_path));
                    }
                }
            }
        }

        /*
            Example usage for $to:
            $to = 'test@mail.ru';
            $to = array('test@mail.ru', 'test2@mail.ru');
            $to = array('John Doe' => 'john@example.com', 'Jane Smith' => 'jane@example.com');
            $to = array(
                'to' => array('John Doe' => 'john@example.com', 'jane@example.com'),
                'cc' => array('cc1@example.com', 'CC Name' => 'cc2@example.com'),
                'bcc' => array('bcc@example.com'),
            );
        */

        if (is_string($to))
        {
            // Single recipient
            $message->setTo($to);
        }
        elseif (is_array($to))
        {
            // Check if it's a simple list of emails or an associative array with 'to', 'cc', 'bcc'
            $has_special_keys = array_intersect_key($to, array('to' => null, 'cc' => null, 'bcc' => null));

            if (empty($has_special_keys))
            {
                // Simple array of recipients (e.g., ['email1@example.com', 'Name' => 'email2@example.com'])
                $message->setTo($to);
            }
            else
            {
                // Array with 'to', 'cc', 'bcc' keys
                foreach ($to as $method => $recipients)
                {
                    $method = strtolower($method); // Ensure method is lowercase for switch

                    switch ($method)
                    {
                        case 'to':
                            $message->setTo($recipients);
                            break;
                        case 'cc':
                            $message->setCc($recipients);
                            break;
                        case 'bcc':
                            $message->setBcc($recipients);
                            break;
                        default:
                            // Log or throw an error for unknown keys
                            Kohana::$log->add(Log::WARNING, 'Unknown recipient type in email config: :type', array(':type' => $method));
                            break;
                    }
                }
            }
        }

        // Handle 'from' address
        if (is_string($from))
        {
            // From without a name
            $message->setFrom($from);
        }
        elseif (is_array($from))
        {
            // From with a name (e.g., ['email@example.com', 'Sender Name'] or ['Sender Name' => 'email@example.com'])
            if (isset($from[0]) && isset($from[1]))
            {
                $message->setFrom($from[0], $from[1]);
            }
            else
            {
                $message->setFrom($from); // SwiftMailer can handle ['Name' => 'email']
            }
        }

        return Email::$mail->send($message);
    }

} // End Email