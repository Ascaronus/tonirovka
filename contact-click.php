<?php
require_once __DIR__ . '/admin/contact_stats_store.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
function stopClick($status) { http_response_code($status); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Allow: POST'); stopClick(405); }
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 512) stopClick(413);
// A browser click must come from this site. Origin is checked even when Referer is absent.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowed = ['https://tonirovka.kh.ua', 'https://www.tonirovka.kh.ua'];
if (preg_match('/^(localhost|127\.0\.0\.1)(:[0-9]+)?$/D', $host)) $allowed[] = 'http://' . $host;
if (!in_array($origin, $allowed, true)) stopClick(403);
$service = $_POST['service'] ?? null; $place = $_POST['place'] ?? null;
if (!is_string($service) || !isset(contactServices()[$service]) || !is_string($place) || !isset(contactPlaces()[$place])) stopClick(400);
try { contactRecord($service, $place); stopClick(204); }
catch (Throwable $e) { error_log('Contact statistics write failed'); stopClick(503); }
