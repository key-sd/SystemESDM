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
        <span class="badge-celula badge-celula-<?= e($reporte["tipo_celula"] ?? 'adultos') ?>">
            <i class="ri-shield-user-line"></i> Célula <?= e(etiquetaTipoCelula($reporte["tipo_celula"] ?? 'adultos')) ?>
        </span>
    </div>

    <?php
    $tipoCel = $reporte["tipo_celula"] ?? "adultos";
    $totAdultos = (int) $reporte["adultos_amigos"] + (int) $reporte["adultos_hermanos"];
    $totJovenes = (int) $reporte["jovenes_amigos"] + (int) $reporte["jovenes_hermanos"];
    $totNinos = (int) $reporte["ninos"];
    $totGrupo = ($tipoCel === "ninos") ? $totNinos : (($tipoCel === "jovenes") ? $totJovenes : $totAdultos);
    $etiquetaGrupo = ($tipoCel === "ninos") ? "Solo niños" : (($tipoCel === "jovenes") ? "Solo jóvenes" : "Solo adultos");
    ?>

    <div class="grid-4 report-detail-stats">
        <div class="stat">
            <div class="label">Total Célula (<?= $etiquetaGrupo ?>)</div>
            <div class="value"><?= $totGrupo ?></div>
        </div>
        <div class="stat">
            <div class="label">Total Global (Asistencia)</div>
            <div class="value" style="color: #2563eb;"><?= $reporte["total_asistencia"] ?></div>
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
        <h3>Asistencia de la reunión</h3>
        <div class="detail-list">
            <div><span>Adultos amigos</span><strong><?= $reporte["adultos_amigos"] ?></strong></div>
            <div><span>Adultos hermanos</span><strong><?= $reporte["adultos_hermanos"] ?></strong></div>
            <div><span>Jóvenes amigos</span><strong><?= $reporte["jovenes_amigos"] ?></strong></div>
            <div><span>Jóvenes hermanos</span><strong><?= $reporte["jovenes_hermanos"] ?></strong></div>
            <div><span>Niños</span><strong><?= $reporte["ninos"] ?></strong></div>
            <div style="border-top: 1px dashed var(--line); margin-top: 6px; padding-top: 6px;">
                <span><strong>Total Célula (<?= $etiquetaGrupo ?>)</strong></span>
                <strong style="color: var(--gold);"><?= $totGrupo ?></strong>
            </div>
            <div>
                <span><strong>Total Global (Todas las personas)</strong></span>
                <strong style="color: #2563eb;"><?= $reporte["total_asistencia"] ?></strong>
            </div>
        </div>
    </section>

    <section class="card">
        <h3>Ofrenda</h3>
        <div class="detail-list">
            <div><span>Iglesia</span><strong>$<?= number_format($reporte["ofrenda_iglesia"], 2) ?></strong></div>
            <div><span>Misiones</span><strong>$<?= number_format($reporte["ofrenda_misiones"], 2) ?></strong></div>
            <div><span>Transporte</span><strong>$<?= number_format($reporte["ofrenda_bus"], 2) ?></strong></div>
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
                <i class="ri-checkbox-circle-<?= $reporte["bautismo_agua"] ? "fill" : "line" ?>"></i>Bautismo en agua
            </div>
        </div>
    </section>

    <section class="card">
        <h3>Observaciones</h3>
        <p class="detail-notes"><?= nl2br(e($reporte["observaciones"] ?: "No se registraron observaciones.")) ?></p>
    </section>
</div>

<?php
$detalleConversiones = [];
$detalleBautismos = [];
$detalleVisitas = [];

if (isset($conexion) && !empty($reporte['id'])) {
    $q = $conexion->prepare('SELECT * FROM reportes_lider_conversiones WHERE reporte_lider_id = ? ORDER BY id ASC');
    $q->execute([(int)$reporte['id']]);
    $detalleConversiones = $q->fetchAll();

    $q = $conexion->prepare('SELECT * FROM reportes_lider_bautismos WHERE reporte_lider_id = ? ORDER BY id ASC');
    $q->execute([(int)$reporte['id']]);
    $detalleBautismos = $q->fetchAll();

    $q = $conexion->prepare('SELECT * FROM reportes_lider_visitas WHERE reporte_lider_id = ? ORDER BY id ASC');
    $q->execute([(int)$reporte['id']]);
    $detalleVisitas = $q->fetchAll();
}
?>

<?php if ($detalleConversiones): ?>
    <section class="card">
        <div class="card-head">
            <div>
                <h3>Detalle de Conversiones / Reconciliaciones</h3>
                <p class="muted">Personas registradas en este reporte</p>
            </div>
            <span class="badge ok"><?= count($detalleConversiones) ?> registradas</span>
        </div>
        <div class="detail-subtable-wrap">
            <table class="detail-subtable">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Decisión</th>
                        <th>Estado Civil</th>
                        <th>Bautismos</th>
                        <th>Nacimiento</th>
                        <th>Contacto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalleConversiones as $dc): ?>
                        <tr>
                            <td><strong><?= e($dc['nombre_completo']) ?></strong></td>
                            <td><span class="badge ok"><?= e(ucfirst($dc['tipo_decision'])) ?></span></td>
                            <td><?= e(ucfirst($dc['estado_civil'])) ?></td>
                            <td>
                                Agua: <?= $dc['bautismo_agua'] ? 'Sí' : 'No' ?> ·
                                E.S.: <?= $dc['bautismo_espiritu'] ? 'Sí' : 'No' ?>
                            </td>
                            <td><?= $dc['fecha_nacimiento'] ? date('d/m/Y', strtotime($dc['fecha_nacimiento'])) : '—' ?></td>
                            <td>
                                <div><i class="ri-phone-line"></i> <?= e($dc['telefono'] ?: '—') ?></div>
                                <small class="muted"><?= e($dc['direccion'] ?: '') ?></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php if ($detalleBautismos): ?>
    <section class="card">
        <div class="card-head">
            <div>
                <h3>Bautismo en Agua</h3>
            </div>
            <span class="badge ok"><?= count($detalleBautismos) ?> <?= count($detalleBautismos) === 1 ? 'candidato' : 'candidatos' ?></span>
        </div>
        <div class="detail-subtable-wrap">
            <table class="detail-subtable">
                <thead>
                    <tr>
                        <th>Nombre del Candidato</th>
                        <th>Célula</th>
                        <th>Estado Civil</th>
                        <th>Fecha Postulación</th>
                        <th>Nacimiento</th>
                        <th>Contacto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalleBautismos as $db): ?>
                        <tr>
                            <td><strong><?= e($db['nombre_completo']) ?></strong></td>
                            <td><span class="badge ok"><?= e(etiquetaTipoCelula($db['tipo_celula'])) ?></span></td>
                            <td><?= e(ucfirst($db['estado_civil'])) ?></td>
                            <td><?= $db['fecha_bautismo'] ? date('d/m/Y', strtotime($db['fecha_bautismo'])) : '—' ?></td>
                            <td><?= $db['fecha_nacimiento'] ? date('d/m/Y', strtotime($db['fecha_nacimiento'])) : '—' ?></td>
                            <td>
                                <div><i class="ri-phone-line"></i> <?= e($db['telefono'] ?: '—') ?></div>
                                <small class="muted"><?= e($db['direccion'] ?: '') ?></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php if ($detalleVisitas): ?>
    <section class="card">
        <div class="card-head">
            <div>
                <h3>Detalle de Visitas Programadas</h3>
                <p class="muted">Visitas pastorales y de seguimiento</p>
            </div>
            <span class="badge ok"><?= count($detalleVisitas) ?> registradas</span>
        </div>
        <div class="detail-subtable-wrap">
            <table class="detail-subtable">
                <thead>
                    <tr>
                        <th>Persona Visitada</th>
                        <th>Célula</th>
                        <th>Visita No.</th>
                        <th>Fecha Visita</th>
                        <th>Bautismos</th>
                        <th>Contacto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalleVisitas as $dv): ?>
                        <tr>
                            <td><strong><?= e($dv['nombre_completo']) ?></strong></td>
                            <td><span class="badge ok"><?= e(etiquetaTipoCelula($dv['tipo_celula'])) ?></span></td>
                            <td><strong>Visita <?= (int)$dv['numero_visita'] ?></strong></td>
                            <td><?= $dv['fecha_visita'] ? date('d/m/Y', strtotime($dv['fecha_visita'])) : '—' ?></td>
                            <td>
                                Agua: <?= $dv['bautismo_agua'] ? 'Sí' : 'No' ?> ·
                                Esp.: <?= $dv['bautismo_espiritu'] ? 'Sí' : 'No' ?>
                            </td>
                            <td>
                                <div><i class="ri-phone-line"></i> <?= e($dv['telefono'] ?: '—') ?></div>
                                <small class="muted"><?= e($dv['direccion'] ?: '') ?></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<section class="card">
    <h3>Comprobante de seguimiento</h3>
    <div class="detail-list">
        <div><span>Enviado por el líder</span><strong class="receipt"><?= fechaBonita($reporte["enviado_en"]) ?></strong></div>
        <div><span>Última corrección del líder</span><strong class="receipt"><?= $reporte["actualizado_en"] ? fechaBonita($reporte["actualizado_en"]) : "Sin correcciones" ?></strong></div>
        <div><span>Visto por supervisor</span><strong class="receipt"><?= fechaBonita($reporte["visto_supervisor_en"]) ?></strong></div>
        <div><span>Visto por administrador</span><strong class="receipt"><?= fechaBonita($reporte["visto_admin_en"]) ?></strong></div>
    </div>
</section>
