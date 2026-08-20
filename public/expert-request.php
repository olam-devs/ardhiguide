<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
$u=require_auth(); if(($u['role']??'')==='admin') redirect('/admin/expert-requests.php');
if($_SERVER['REQUEST_METHOD']==='POST'){
  $type=(string)($_POST['expert_type']??'');$r=trim((string)($_POST['region_code']??''));$d=trim((string)($_POST['district_code']??''));$w=trim((string)($_POST['ward_code']??''));$desc=trim((string)($_POST['description']??''));
  if(!in_array($type,['surveyor','valuer','town_planner','advocate'],true)||!location_selection_valid($r,$d,$w)||strlen($desc)<10){flash_set('err','Choose an expert, valid location, and describe what you need.');}
  else{db()->prepare('INSERT INTO expert_requests (buyer_user_id,expert_type,region_code,district_code,ward_code,description) VALUES (?,?,?,?,?,?)')->execute([(int)$u['id'],$type,$r,$d,$w,$desc]);flash_set('ok','Expert request sent. Admin will review and match a verified expert.');redirect('/my-expert-requests.php');}
}
$regions=location_regions();ob_start(); ?>
<div class="card pad reveal" style="max-width:820px;margin:0 auto"><div class="kicker">Professional support</div><h1>Request a property expert</h1><p class="sub">Tell us what you need and where. An admin will manually match an appropriate verified professional.</p>
<form method="post" class="stack"><div><label>Expert type</label><select name="expert_type" required><option value="">Choose expert</option><option value="surveyor">Surveyor</option><option value="valuer">Valuer</option><option value="town_planner">Town planner</option><option value="advocate">Advocate</option></select></div>
<div class="location-grid" data-location-picker data-locations-url="<?= APP_BASE_URL ?>/locations-api.php"><div><label>Region</label><select name="region_code" data-region required><option value="">Choose region</option><?php foreach($regions as $row):?><option value="<?= h($row['code']) ?>"><?= h($row['name']) ?></option><?php endforeach;?></select></div><div><label>District / council</label><select name="district_code" data-district required disabled><option value="">Choose district</option></select></div><div><label>Ward</label><select name="ward_code" data-ward required disabled><option value="">Choose ward</option></select></div></div>
<div><label>Describe what you need</label><textarea name="description" required minlength="10" maxlength="4000" placeholder="Explain the property, task, timeline, and any concerns..."></textarea></div><div style="display:flex;gap:.7rem;flex-wrap:wrap"><button class="btn" type="submit">Send request</button><a class="btn secondary" href="<?= APP_BASE_URL ?>/my-expert-requests.php">My requests</a></div></form></div>
<?php $content=ob_get_clean();$title='Request an expert. Ardhi Way';require __DIR__.'/_layout.php';
