-- Remove payment-provider branding from public payment guide copy (user-facing subtitles only).
SET NAMES utf8mb4;

UPDATE payment_categories
SET subtitle = 'Airtel Money, M-Pesa, Mixx by Yas, and Halotel — secure online checkout'
WHERE title = 'Pay with mobile money'
  AND (subtitle LIKE '%Snippe%' OR subtitle LIKE '%snippe%');
