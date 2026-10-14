<?php defined('SYSPATH') OR die('No direct access allowed.');

/** ORM auth with account blocking and session-epoch revocation. */
class Auth_ORM extends Kohana_Auth_ORM
{
    protected function _login($user, $password, $remember)
    {
        if ( ! is_object($user))
        {
            $lookup=ORM::factory('User');
            $lookup->where($lookup->unique_key($user),'=',$user)->find();
            $user=$lookup;
        }
        if ($user instanceof Model_User AND $user->loaded() AND ($user->status ?: 'active') !== 'active') return FALSE;
        return parent::_login($user,$password,$remember);
    }

    public function force_login($user, $mark_session_as_forced = FALSE)
    {
        $resolved=is_object($user)?$user:ORM::factory('User',array('username'=>$user));
        if ($resolved->loaded() AND ($resolved->status ?: 'active') !== 'active')
            throw new Kohana_Exception('Blocked user cannot be logged in');
        return parent::force_login($resolved,$mark_session_as_forced);
    }

    protected function complete_login($user)
    {
        $result=parent::complete_login($user);
        $this->_session->set('erp100_session_version',(int)($user->session_version ?: 1));
        return $result;
    }

    public function get_user($default = NULL)
    {
        $stored=parent::get_user($default);
        if ($stored === $default OR ! $stored instanceof Model_User OR ! $stored->loaded()) return $stored;
        $fresh=ORM::factory('User',(int)$stored->id);
        $version=(int)$this->_session->get('erp100_session_version',1);
        if ( ! $fresh->loaded() OR ($fresh->status ?: 'active') !== 'active' OR (int)($fresh->session_version ?: 1) !== $version)
        {
            $this->logout(FALSE,TRUE);
            $this->_session->delete('erp100_session_version');
            return $default;
        }
        return $fresh;
    }

    public function logout($destroy = FALSE, $logout_all = FALSE)
    {
        $this->_session->delete('erp100_session_version');
        return parent::logout($destroy,$logout_all);
    }
}
