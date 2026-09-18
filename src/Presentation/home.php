<!doctype html>
<html lang="lt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($config['name']) ?> – pasirink miestą</title>
<link rel="stylesheet" href="assets/app.css">
</head>
<body>
<main class="page">
<header>
  <span class="eyebrow">MYGAME • LIETUVA</span>
  <h1>Kur pradėsi savo verslą?</h1>
  <p>Pasirink miestą. Didesnė rinka vėliau reikš daugiau klientų, bet ir didesnę konkurenciją bei sąnaudas.</p>
</header>

<section class="layout">
  <div class="map-card">
    <div class="map" id="map">
      <svg class="country" viewBox="0 0 1000 650" aria-label="Stilizuotas Lietuvos žemėlapis">
        <path d="M90 235 L132 155 L228 126 L302 78 L397 92 L475 58 L570 82 L647 62 L745 105 L833 103 L906 157 L927 238 L891 307 L921 382 L870 455 L799 476 L742 548 L650 563 L575 608 L480 579 L400 603 L321 558 L233 570 L170 514 L109 489 L87 409 L49 341 Z"/>
      </svg>
      <?php foreach ($cities as $city):
        $size = max(8, min(24, 7 + log(max($city->population, 5000) / 5000, 1.8)));
      ?>
      <button class="city-dot" style="left:<?= $city->x ?>%;top:<?= $city->y ?>%;width:<?= $size ?>px;height:<?= $size ?>px"
        data-city="<?= htmlspecialchars($city->name) ?>" data-pop="<?= $city->population ?>"
        aria-label="<?= htmlspecialchars($city->name) ?>"></button>
      <?php endforeach; ?>
    </div>
  </div>

  <aside class="panel">
    <div class="panel-label">STARTO MIESTAS</div>
    <h2 id="cityName"><?= $selected ? htmlspecialchars($selected) : 'Pasirink miestą žemėlapyje' ?></h2>
    <div class="stat"><span>Gyventojai</span><strong id="population">—</strong></div>
    <div class="stat"><span>Minimalus dydis</span><strong>5 000</strong></div>
    <form method="post" id="cityForm">
      <input type="hidden" name="city" id="cityInput">
      <button class="start" id="startButton" disabled>Pradėti verslą šiame mieste</button>
    </form>
    <?php if ($selected): ?><p class="saved">Pasirinkta: <strong><?= htmlspecialchars($selected) ?></strong></p><?php endif; ?>
  </aside>
</section>
</main>
<script src="assets/app.js"></script>
</body>
</html>
