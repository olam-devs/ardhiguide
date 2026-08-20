<?php

declare(strict_types=1);

/** @return list<array{code:string,name:string}> */
function location_regions(): array {
  return db()->query('SELECT code, name FROM locations_regions ORDER BY name')->fetchAll();
}
/** @return list<array{code:string,name:string}> */
function location_districts(string $regionCode): array {
  $st = db()->prepare('SELECT code, name FROM locations_districts WHERE region_code = ? ORDER BY name');
  $st->execute([$regionCode]);
  return $st->fetchAll();
}

/** @return list<array{code:string,name:string}> */
function location_wards(string $regionCode, string $districtCode): array {
  $st = db()->prepare('SELECT code, name FROM locations_wards WHERE region_code = ? AND district_code = ? ORDER BY name');
  $st->execute([$regionCode, $districtCode]);
  return $st->fetchAll();
}

function location_selection_valid(string $regionCode, string $districtCode, string $wardCode): bool {
  if ($regionCode === '' || $districtCode === '' || $wardCode === '') return false;
  $st = db()->prepare(
    'SELECT 1 FROM locations_wards WHERE region_code = ? AND district_code = ? AND code = ? LIMIT 1'
  );
  $st->execute([$regionCode, $districtCode, $wardCode]);
  return (bool)$st->fetchColumn();
}

/** @return array{region:string,district:string,ward:string}|null */
function location_names(string $regionCode, string $districtCode, string $wardCode): ?array {
  $st = db()->prepare(
    'SELECT r.name AS region, d.name AS district, w.name AS ward
     FROM locations_wards w
     JOIN locations_districts d ON d.region_code = w.region_code AND d.code = w.district_code
     JOIN locations_regions r ON r.code = w.region_code
     WHERE w.region_code = ? AND w.district_code = ? AND w.code = ? LIMIT 1'
  );
  $st->execute([$regionCode, $districtCode, $wardCode]);
  $row = $st->fetch();
  return $row ?: null;
}
