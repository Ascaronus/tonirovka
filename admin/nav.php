<?php
$admin_links = ['index.php'=>'📊 Главная', 'prices.php'=>'💰 Цены', 'gallery.php'=>'🖼️ Галерея', 'films.php'=>'🎨 Пленки', 'content.php'=>'📝 Контент', 'guide.php'=>'📖 Выбор плёнки', 'contact-stats.php'=>'📈 Нажатия контактов', 'seo-auto.php'=>'🚀 SEO Автоматика', 'settings.php'=>'⚙️ Настройки', 'logs.php'=>'📋 Логи'];
foreach ($admin_links as $file => $label): ?>
<a href="<?=htmlspecialchars($file)?>"<?=basename($_SERVER['SCRIPT_NAME'] ?? '') === $file ? ' aria-current="page" style="background:#0056b3;box-shadow:inset 0 -3px 0 #fff"' : ''?>><?=htmlspecialchars($label)?></a>
<?php endforeach; ?>
