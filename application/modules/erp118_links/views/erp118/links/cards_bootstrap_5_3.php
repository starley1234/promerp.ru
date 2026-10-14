<div class="container py-4">
	<div class="row">
		<div class="col-md-12">
          <div class="card-body">
            <div class="row">
              <?php if (count($user_links) > 0): ?>
              <?php foreach ($user_links as $link): ?>
              <div class="col-md-4 mb-3">
                <div class="card h-100 position-relative">
                  <button type="button" class="btn-close position-absolute top-0 end-0 m-2 delete-link-btn" aria-label="Удалить" data-link-id="<?php echo $link->id; ?>"></button>
                  <div class="card-body d-flex flex-column">
                    <h5 class="card-title d-flex align-items-center mb-2">
                      <i class="<?php echo HTML::chars($link->icon_class); ?> me-2 fs-4" data-ui-color="<?php echo HTML::chars($link->color); ?>"></i>
                      <a href="<?php echo HTML::chars($link->url); ?>" class="text-decoration-none" data-ui-color="<?php echo HTML::chars($link->color); ?>">
                        <?php echo HTML::chars($link->title); ?>
                      </a>
                    </h5>
                    <?php if (!empty($link->description)): ?>
                    <p class="card-text text-muted flex-grow-1"><?php echo HTML::chars($link->description); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($link->group_name)): ?>
                    <div class="mt-auto text-end">
                        <span class="badge text-bg-light border"><?php echo HTML::chars($link->group_name); ?></span>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
              <?php else: ?>
              
              <?php endif; ?>
            </div>
		</div>
	</div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.delete-link-btn').forEach(button => {
        button.addEventListener('click', function() {
            const linkId = this.dataset.linkId;
            //if (confirm('Вы уверены, что хотите удалить эту ссылку?')) {
			if (1==1) {
                fetch('/links/delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `id=${linkId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        this.closest('.col-md-4').remove();
                        // Можно добавить уведомление об успешном удалении
                    } else {
                        alert('Ошибка при удалении ссылки: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Ошибка:', error);
                    alert('Произошла ошибка при удалении ссылки.');
                });
            }
        });
    });
});
</script>