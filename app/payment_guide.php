<?php

declare(strict_types=1);

/**
 * Default published payment guide (online checkout).
 * Used when seeding the database and as fallback copy on the pay page.
 *
 * @return list<array{title:string,subtitle:?string,sort_order:int,steps:list<string>}>
 */
function payment_guide_snippe_defaults(): array {
  return [
    [
      'title' => 'Pay with mobile money',
      'subtitle' => 'Airtel Money, M-Pesa, Mixx by Yas, and Halotel — secure online checkout',
      'sort_order' => 1,
      'steps' => [
        'After you submit a listing, open **My listings** and tap **Pay** on the row that shows a pending fee.',
        'Check the amount and your unique **payment code** on screen. Admin may set a different fee or assign the phone that receives the prompt.',
        'On the pay page, enter the mobile number that will get the USSD prompt (unless admin locked a number for you).',
        'Tap **Pay with mobile money**. A payment request is sent to your phone within a few seconds.',
        'Approve the prompt on your phone and enter your mobile money **PIN only on that official USSD screen** — never share your PIN with anyone, including staff.',
        'This page updates automatically when payment succeeds (usually under a minute). If nothing happens, refresh or try once more. Do not pay twice for the same listing.',
      ],
    ],
    [
      'title' => 'Pay with card',
      'subtitle' => 'Visa, Mastercard, and local debit cards',
      'sort_order' => 2,
      'steps' => [
        'From the same pay page, tap **Pay with card**.',
        'You are redirected to a **secure checkout page** (HTTPS) to enter your card details. Pay only on that page — never send card numbers or CVV over WhatsApp, SMS, or email.',
        'After you finish, you return to Ardhi Way. Confirmation may take a short moment while we verify the payment.',
        'Your listing fee is marked **paid** once verification completes. You can track status under **My listings**.',
      ],
    ],
    [
      'title' => 'Payment code and listing fee',
      'subtitle' => 'How we match your payment to your plot',
      'sort_order' => 3,
      'steps' => [
        'Each listing has a fee based on its package: **Basic**, **Featured**, or **Premium** (shown when you submit). Admin can adjust the amount before you pay.',
        'Your **payment code** (for example AG-12-ABCD1234) is included in the payment request so our team can match it to your listing.',
        'Online payments must be at least **500 TZS**. If payment fails, read the message on screen and try again, or contact us on WhatsApp.',
        'When payment is **paid**, our team can review and approve your listing for the public browse page.',
      ],
    ],
    [
      'title' => 'Payment problems or help',
      'subtitle' => 'We are here if online checkout does not work',
      'sort_order' => 4,
      'steps' => [
        'Payment declined or expired: start a new payment from the pay page. Only complete one successful payment per listing.',
        'Wrong phone number: use the correct number for the wallet you want to pay from, or ask admin to update the assigned prompt number.',
        'Paid but status still pending: wait one minute and refresh **My listings**. If it still shows pending, message us on WhatsApp with your payment code and a screenshot of the confirmation SMS.',
        'Our team can also mark a payment as received manually after verifying your proof (for exceptional cases only).',
      ],
    ],
  ];
}

/**
 * Insert default online-payment guide categories if the guide is empty or being replaced.
 * Returns true if seeding ran.
 */
function payment_guide_seed_snippe_defaults(bool $replaceLegacy = true): bool {
  $count = (int)db()->query('SELECT COUNT(*) FROM payment_categories')->fetchColumn();
  if ($count > 0 && !$replaceLegacy) {
    return false;
  }

  if ($replaceLegacy && $count > 0) {
    db()->exec('DELETE FROM payment_steps');
    db()->exec('DELETE FROM payment_categories');
  } elseif ($count > 0) {
    $published = (int)db()->query('SELECT COUNT(*) FROM payment_categories WHERE is_published = 1')->fetchColumn();
    if ($published > 0) {
      return false;
    }
  }

  $insCat = db()->prepare(
    'INSERT INTO payment_categories (title, subtitle, is_published, sort_order) VALUES (?,?,1,?)'
  );
  $insStep = db()->prepare(
    'INSERT INTO payment_steps (category_id, body, sort_order) VALUES (?,?,?)'
  );

  foreach (payment_guide_snippe_defaults() as $cat) {
    $insCat->execute([
      (string)$cat['title'],
      $cat['subtitle'] ?? null,
      (int)$cat['sort_order'],
    ]);
    $catId = (int)db()->lastInsertId();
    $sort = 1;
    foreach ($cat['steps'] as $body) {
      $insStep->execute([$catId, $body, $sort++]);
    }
  }
  return true;
}

/**
 * Load published categories with steps for the public payment guide.
 *
 * @return list<array{id:int,title:string,subtitle:?string,steps:list<array{body:string}>}>
 */
function payment_guide_load_published(): array {
  $cats = db()->query(
    'SELECT id, title, subtitle FROM payment_categories WHERE is_published = 1 ORDER BY sort_order ASC, id ASC'
  )->fetchAll();

  if (!$cats && snippe_enabled()) {
    payment_guide_seed_snippe_defaults(true);
    $cats = db()->query(
      'SELECT id, title, subtitle FROM payment_categories WHERE is_published = 1 ORDER BY sort_order ASC, id ASC'
    )->fetchAll();
  }

  if (!$cats) {
    return [];
  }

  $ids = array_map(static fn($c) => (int)$c['id'], $cats);
  $in = implode(',', array_fill(0, count($ids), '?'));
  $st = db()->prepare(
    "SELECT category_id, body FROM payment_steps WHERE category_id IN ($in) ORDER BY sort_order ASC, id ASC"
  );
  $st->execute($ids);
  $stepsByCat = [];
  foreach ($st->fetchAll() as $row) {
    $stepsByCat[(int)$row['category_id']][] = ['body' => (string)$row['body']];
  }

  $out = [];
  foreach ($cats as $c) {
    $cid = (int)$c['id'];
    $out[] = [
      'id' => $cid,
      'title' => (string)$c['title'],
      'subtitle' => isset($c['subtitle']) ? (string)$c['subtitle'] : null,
      'steps' => $stepsByCat[$cid] ?? [],
    ];
  }
  return $out;
}

/** Render step text; supports **bold** markers after escaping. */
function payment_guide_format_step(string $body): string {
  $escaped = h($body);
  $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $escaped) ?? $escaped;
  return nl2br($html);
}

/** Short summary lines for the pay-listing page (supports **bold** via payment_guide_format_step). */
function payment_guide_pay_page_summary(): array {
  if (snippe_enabled()) {
    return [
      'Pay on this page with **mobile money** (USSD prompt on your phone) or **card** — only use the buttons here, not a separate till or transfer unless we ask you to.',
      'Enter your mobile money **PIN only on the prompt on your phone**. Never share your PIN or card details in chat.',
      'Your **payment code** is included automatically so we can match your payment to your listing.',
      'When payment succeeds, status updates to **paid**. Full steps and safety tips are on the **Payment guide** page.',
    ];
  }
  return [
    'Online checkout is temporarily unavailable. Contact us on WhatsApp to complete your listing fee.',
    'Have your **payment code** ready when you message us.',
  ];
}
