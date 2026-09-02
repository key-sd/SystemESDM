<main class="main">
    <header class="topbar">
        <div>
            <div class="eyebrow">Elim Santiago de María</div>
            <h1 class="page-title"><?= e($titulo) ?></h1>
            <p class="page-sub"><?= e($subtitulo ?? "") ?></p>
        </div>

        <button class="btn btn-soft mobile-menu" id="mobileMenu" type="button">
            <i class="ri-menu-line"></i>
        </button>
    </header>

    <?php if ($mensajeFlash = tomarFlash()): ?>
        <div class="alert alert-<?= $mensajeFlash[0] === "success"
            ? "success"
            : "danger" ?>">
            <?= e($mensajeFlash[1]) ?>
        </div>
    <?php endif; ?>

