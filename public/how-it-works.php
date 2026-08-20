<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

ob_start();
?>
  <div class="card pad reveal" style="margin-bottom:1rem;border-color:rgba(139,69,19,.2)">
    <div class="kicker">Platform guide</div>
    <h1>How Ardhi Way works</h1>
    <p class="lead">Use this page as the official walkthrough for property seekers, sellers, agents, and property experts. Verification, private requests, guided contact, and expert support are explained in plain language.</p>
    <div style="margin-top:1rem">
      <a class="btn" href="<?= APP_BASE_URL ?>/index.php#browse">Browse listings</a>
      <a class="btn secondary" href="<?= APP_BASE_URL ?>/register.php">Create account</a>
    </div>
  </div>

  <div class="instr-columns" style="margin-bottom:1rem">
    <div class="instr-card reveal">
      <h3>For property seekers</h3>
      <ul>
        <li>Register as a <strong>property seeker</strong> to save requests, chat with admin, and receive direct property links.</li>
        <li>Use <strong>Browse</strong> and filters (region, property type, keywords) to find approved property.</li>
        <li>Open an approved listing and use <strong>Confirm interest</strong>. Ardhi Way coordinates the next step without exposing private seller details.</li>
        <li>Badges (e.g. docs submitted, reviewed) are <strong>internal review levels</strong>, not a government guarantee of ownership. Always do your own due diligence.</li>
      </ul>
    </div>
    <div class="instr-card reveal">
      <h3>For sellers &amp; agents</h3>
      <ul>
        <li>Register as <strong>seller</strong> or <strong>agent</strong>, then <strong>Submit listing</strong> with title, location, price, photos, and optional private documents (PDF/images).</li>
        <li>Every listing is free to submit and must be approved by admin before property seekers can see it.</li>
        <li>Track everything under <strong>My listings</strong>: review status, property-request count, and documents. Use <strong>Preview</strong> before approval; <strong>Public view</strong> works only after admin approval.</li>
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
      <li>Wait while admin reviews the listing and its supporting documents. When approved, it can appear in search and admin can choose it for the homepage.</li>
      <li>Monitor <strong>My listings</strong> for status changes and property-seeker activity.</li>
    </ol>
    <div class="instr-note">
      <strong>Important:</strong> This software does not replace lawyers, surveyors, or government land registries. It organises listings, leads, and internal review, not legal title certification.
    </div>
  </div>

<?php
$content = ob_get_clean();
$title = 'How it works. Ardhi Way';
require __DIR__ . '/_layout.php';
