-- Создание таблицы для мониторинга SEO
CREATE TABLE IF NOT EXISTS `seo_monitoring` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `timestamp` datetime NOT NULL,
  `domain` varchar(255) NOT NULL,
  `overall_status` enum('ok','warning','error') NOT NULL,
  `meta_tags_status` enum('ok','warning','error') NOT NULL,
  `images_status` enum('ok','warning','error') NOT NULL,
  `sitemap_status` enum('ok','warning','error') NOT NULL,
  `structured_data_status` enum('ok','warning','error') NOT NULL,
  `page_speed_status` enum('ok','warning','error') NOT NULL,
  `mobile_friendly_status` enum('ok','warning','error') NOT NULL,
  `security_status` enum('ok','warning','error') NOT NULL,
  `results_json` longtext NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_timestamp` (`timestamp`),
  KEY `idx_overall_status` (`overall_status`),
  KEY `idx_domain` (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Добавление индексов для быстрого поиска
CREATE INDEX idx_meta_tags_status ON seo_monitoring(meta_tags_status);
CREATE INDEX idx_images_status ON seo_monitoring(images_status);
CREATE INDEX idx_sitemap_status ON seo_monitoring(sitemap_status);
CREATE INDEX idx_structured_data_status ON seo_monitoring(structured_data_status);
CREATE INDEX idx_page_speed_status ON seo_monitoring(page_speed_status);
CREATE INDEX idx_mobile_friendly_status ON seo_monitoring(mobile_friendly_status);
CREATE INDEX idx_security_status ON seo_monitoring(security_status);
