-- Replace legacy manual payment instructions with Snippe online checkout guide.
-- Safe to run once on production after Snippe integration.
-- Removes all previous payment_categories / payment_steps and inserts the new guide.

SET NAMES utf8mb4;

DELETE FROM payment_steps;
DELETE FROM payment_categories;

INSERT INTO payment_categories (title, subtitle, is_published, sort_order) VALUES
(
  'Pay with mobile money',
  'Airtel Money, M-Pesa, Mixx by Yas, and Halotel — secure online checkout',
  1,
  1
);
SET @c1 = LAST_INSERT_ID();
INSERT INTO payment_steps (category_id, body, sort_order) VALUES
(@c1, 'After you submit a listing, open My listings and tap Pay on the row that shows a pending fee.', 1),
(@c1, 'Check the amount and your unique payment code on screen. Admin may set a different fee or assign the phone that receives the prompt.', 2),
(@c1, 'On the pay page, enter the mobile number that will get the USSD prompt (unless admin locked a number for you).', 3),
(@c1, 'Tap Pay with mobile money. A payment request is sent to your phone within a few seconds.', 4),
(@c1, 'Approve the prompt on your phone and enter your mobile money PIN. The pay page updates automatically when payment succeeds.', 5),
(@c1, 'If nothing happens, refresh the page or try again. Do not pay twice for the same listing.', 6);

INSERT INTO payment_categories (title, subtitle, is_published, sort_order) VALUES
(
  'Pay with card',
  'Visa, Mastercard, and local debit cards',
  1,
  2
);
SET @c2 = LAST_INSERT_ID();
INSERT INTO payment_steps (category_id, body, sort_order) VALUES
(@c2, 'From the same pay page, tap Pay with card.', 1),
(@c2, 'You are redirected to a secure checkout page to enter your card details.', 2),
(@c2, 'After you finish, you return to Ardhi Guide. Confirmation may take a short moment.', 3),
(@c2, 'Your listing fee is marked paid once verification completes. Track status under My listings.', 4);

INSERT INTO payment_categories (title, subtitle, is_published, sort_order) VALUES
(
  'Payment code and listing fee',
  'How we match your payment to your plot',
  1,
  3
);
SET @c3 = LAST_INSERT_ID();
INSERT INTO payment_steps (category_id, body, sort_order) VALUES
(@c3, 'Each listing has a fee based on its package: Basic, Featured, or Premium. Admin can adjust the amount before you pay.', 1),
(@c3, 'Your payment code (for example AG-12-ABCD1234) is included in the online payment request automatically.', 2),
(@c3, 'Online payments must be at least 500 TZS. If payment fails, read the message on screen and try again.', 3),
(@c3, 'When payment is paid, our team can review and approve your listing for the public browse page.', 4);

INSERT INTO payment_categories (title, subtitle, is_published, sort_order) VALUES
(
  'Payment problems or help',
  'If online checkout does not work',
  1,
  4
);
SET @c4 = LAST_INSERT_ID();
INSERT INTO payment_steps (category_id, body, sort_order) VALUES
(@c4, 'Payment declined or expired: start a new payment from the pay page. Only complete one successful payment per listing.', 1),
(@c4, 'Wrong phone number: use the number for the wallet you pay from, or ask admin to update the assigned prompt number.', 2),
(@c4, 'Paid but status still pending: wait one minute and refresh My listings. If still pending, WhatsApp us with your payment code and confirmation SMS screenshot.', 3),
(@c4, 'Our team can mark a payment as received manually after verifying proof in exceptional cases only.', 4);
