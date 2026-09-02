<div class="toolbar">
    <a class="btn btn-soft" href="<?= e($urlVolver) ?>">
        <i class="ri-arrow-left-line"></i> Volver a reportes
    </a>
</div>

<section class="card">
    <div class="card-head">
        <div>
            <p class="eyebrow">Reporte semanal de líder</p>
            <h3><?= e($reporte["celula"]) ?></h3>
            <p class="muted">
                <?= e($reporte["nombre_completo"]) ?> · <?= e($reporte["sector"]) ?>
                · Semana del <?= date("d/m/Y", strtotime($reporte["fecha_reporte"])) ?>
            </p>
        </div>
        <span class="badge ok"><?= e(ucfirst($reporte["tipo_celula"])) ?></span>
    </div>

    <div class="grid-3 report-detail-stats">
        <div class="stat">
            <div class="label">Asistencia total</div>
            <div class="value"><?= $reporte["total_asistencia"] ?></div>
        </div>
        <div class="stat">
            <div class="label">Ofrenda total</div>
            <div class="value">$<?= number_format($reporte["ofrenda_total"], 2) ?></div>
        </div>
        <div class="stat">
            <div class="label">Enviado</div>
            <div class="hint receipt"><?= fechaBonita($reporte["enviado_en"]) ?></div>
        </div>
    </div>
</section>

<div class="grid-2">
    <section class="card">
        <h3>Asistencia</h3>
        <div class="detail-list">
            <div><span>Adultos amigos</span><strong><?= $reporte["adultos_amigos"] ?></strong></div>
            <div><span>Adultos hermanos</span><strong><?= $reporte["adultos_hermanos"] ?></strong></div>
            <div><span>Jóvenes amigos</span><strong><?= $reporte["jovenes_amigos"] ?></strong></div>
            <div><span>Jóvenes hermanos</span><strong><?= $reporte["jovenes_hermanos"] ?></strong></div>
            <div><span>Niños</span><strong><?= $reporte["ninos"] ?></strong></div>
        </div>
    </section>

    <section class="card">
        <h3>Ofrenda</h3>
        <div class="detail-list">
            <div><span>Iglesia</span><strong>$<?= number_format($reporte["ofrenda_iglesia"], 2) ?></strong></div>
            <div><span>Misiones</span><strong>$<?= number_format($reporte["ofrenda_misiones"], 2) ?></strong></div>
            <div><span>Bus</span><strong>$<?= number_format($reporte["ofrenda_bus"], 2) ?></strong></div>
        </div>
    </section>
</div>

<div class="grid-2">
    <section class="card">
        <h3>Resultados de la reunión</h3>
        <div class="check-grid">
            <div class="check-card <?= $reporte["conversion"] > 0 ? "is-checked" : "" ?>">
                <i class="ri-group-line"></i> <?= $reporte["conversion"] ?> conversión<?= $reporte["conversion"] == 1 ? "" : "es" ?>
            </div>
            <div class="check-card <?= $reporte["visita_programada"] ? "is-checked" : "" ?>">
                <i class="ri-checkbox-circle-<?= $reporte["visita_programada"] ? "fill" : "line" ?>"></i> Visita programada
            </div>
            <div class="check-card <?= $reporte["bautismo_agua"] ? "is-checked" : "" ?>">
                <i class="ri-checkbox-circle-<?= $reporte["bautismo_agua"] ? "fill" : "line" ?>"></i> Bautismo en agua
            </div>
        </div>
    </section>

    <section class="card">
        <h3>Observaciones</h3>
        <p class="detail-notes"><?= nl2br(e($reporte["observaciones"] ?: "No se registraron observaciones.")) ?></p>
    </section>
</div>

<section class="card">
    <h3>Comprobante de seguimiento</h3>
    <div class="detail-list">
        <div><span>Enviado por el líder</span><strong class="receipt"><?= fechaBonita($reporte["enviado_en"]) ?></strong></div>
        <div><span>Última corrección del líder</span><strong class="receipt"><?= $reporte["actualizado_en"] ? fechaBonita($reporte["actualizado_en"]) : "Sin correcciones" ?></strong></div>
        <div><span>Visto por supervisor</span><strong class="receipt"><?= fechaBonita($reporte["visto_supervisor_en"]) ?></strong></div>
        <div><span>Visto por administrador</span><strong class="receipt"><?= fechaBonita($reporte["visto_admin_en"]) ?></strong></div>
    </div>
</section>
