-- Таблица для хранения SEO автоматических настроек
CREATE TABLE IF NOT EXISTS `seo_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `auto_meta_update` tinyint(1) NOT NULL DEFAULT 1,
  `auto_sitemap_update` tinyint(1) NOT NULL DEFAULT 1,
  `auto_image_optimization` tinyint(1) NOT NULL DEFAULT 1,
  `auto_keywords_generation` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Вставляем настройки по умолчанию
INSERT INTO `seo_settings` (`auto_meta_update`, `auto_sitemap_update`, `auto_image_optimization`, `auto_keywords_generation`) 
VALUES (1, 1, 1, 1)
ON DUPLICATE KEY UPDATE `auto_meta_update`=1, `auto_sitemap_update`=1, `auto_image_optimization`=1, `auto_keywords_generation`=1;

