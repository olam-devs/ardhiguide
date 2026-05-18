<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

ob_start();
?>
  <div class="card pad reveal" style="margin-bottom:1rem;border-color:rgba(139,69,19,.2)">
    <div class="kicker">Platform guide</div>
    <h1>How Ardhi Guide works</h1>
    <p class="lead">Use this page as the official walkthrough for buyers, sellers, agents, and admins. Verification and payment are explained in plain language.</p>
    <div style="margin-top:1rem">
      <a class="btn" href="<?= APP_BASE_URL ?>/index.php#browse">Browse listings</a>
      <a class="btn secondary" href="<?= APP_BASE_URL ?>/register.php">Create account</a>
    </div>
  </div>

  <div class="instr-columns" style="margin-bottom:1rem">
    <div class="instr-card reveal">
      <h3>For buyers</h3>
      <ul>
        <li>Register as a <strong>buyer</strong> (optional but saves your enquiries under <strong>My enquiries</strong>).</li>
        <li>Use <strong>Browse</strong> and filters (region, category, keywords) to find approved land.</li>
        <li>Open a listing, read details and badges, then use <strong>Enquire</strong>. Your details are stored as a lead; you are sent to WhatsApp to continue.</li>
        <li>Badges (e.g. docs submitted, reviewed) are <strong>internal review levels</strong>, not a government guarantee of ownership. Always do your own due diligence.</li>
      </ul>
    </div>
    <div class="instr-card reveal">
      <h3>For sellers &amp; agents</h3>
      <ul>
        <li>Register as <strong>seller</strong> or <strong>agent</strong>, then <strong>Submit listing</strong> with title, location, price, photos, and optional private documents (PDF/images).</li>
        <li>Choose a <strong>listing package</strong> (Basic, Featured, or Premium). You will get a <strong>payment code</strong> on the pay page, plus steps from the published payment guide.</li>
        <li>Track everything under <strong>My listings</strong>: status, leads, document count, fee status. Use <strong>Preview</strong> before approval; <strong>Public view</strong> works only after admin approval.</li>
        <li>Add more verification files anytime under <strong>Documents</strong> for admin review.</li>
      </ul>
    </div>
    <div class="instr-card reveal">
      <h3>Verification &amp; trust</h3>
      <ul>
        <li>Listings go through statuses: <strong>submitted</strong> → <strong>under review</strong> → <strong>approved</strong> or <strong>rejected</strong>.</li>
        <li>Admin can set <strong>verification badges</strong> (identity, documents submitted/reviewed, survey confirmed, etc.). Each badge has a specific meaning in your operations playbook.</li>
        <li>Private uploads are not shown on the public page; only you and admins can download them from secure links after login.</li>
      </ul>
    </div>
  </div>

  <div class="card pad reveal" style="margin-bottom:1rem">
    <h2>Step-by-step: first-time seller</h2>
    <ol class="instr-steps">
      <li>Register and log in as seller or agent.</li>
      <li>Complete <strong>Submit listing</strong> with accurate location and price; add clear photos.</li>
        <li>Upload optional <strong>verification documents</strong> such as title-related files or sketches, whatever your process allows.</li>
      <li>Complete the <strong>listing fee</strong> on the pay page; send proof via WhatsApp if required.</li>
      <li>Wait for admin review. When approved, your listing appears on <strong>Browse</strong> and buyers can enquire.</li>
      <li>Monitor <strong>My listings</strong> for lead counts and follow up on WhatsApp.</li>
    </ol>
    <div class="instr-note">
      <strong>Important:</strong> This software does not replace lawyers, surveyors, or government land registries. It organises listings, leads, and internal review, not legal title certification.
    </div>
  </div>

  <div class="card pad reveal" style="margin-bottom:1rem">
    <h2>Payments</h2>
    <ul class="sub" style="margin:0;padding-left:1.2rem;line-height:1.75">
      <li>Fees are set by package (Basic, Featured, Premium) at submit time.</li>
      <li>Pay online from <strong>My listings</strong> → <strong>Pay</strong>: <strong>mobile money</strong> (USSD prompt on your phone) or <strong>card</strong> on our secure checkout. Your payment code is included automatically. Never share your PIN or card details over chat.</li>
      <li>Full step-by-step help is on the public <strong>Payment guide</strong> (admins can edit categories under <strong>Payment instructions</strong>).</li>
      <li>Successful online payments are marked <strong>paid</strong> automatically. Admins can also mark <strong>paid</strong> or <strong>waived</strong> manually after verifying proof.</li>
    </ul>
  </div>

<?php
$content = ob_get_clean();
$title = 'How it works. Ardhi Guide';
require __DIR__ . '/_layout.php';
