<?php
// Shared form partial for create.php and edit.php
// Requires: $session array with 'title', 'mode', 'predefined_symbols'
// Requires: $existingJs (JSON-encoded symbol array for JS)
?>
<div class="row g-4">

    <!-- Grundeinstellungen -->
    <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header fw-semibold bg-white">
                <i class="bi bi-sliders me-2 text-secondary"></i>Grundeinstellungen
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <label class="form-label fw-semibold">Titel <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" autofocus maxlength="255"
                           value="<?= e($session['title']) ?>"
                           placeholder="z.B. Wie fühlst du dich heute?">
                </div>

                <div class="mb-2">
                    <label class="form-label fw-semibold">Modus</label>
                </div>

                <?php
                $modes = [
                    'symbols' => ['Nur Symbole', 'images',  'primary',
                        'Admin wählt bis zu 20 Symbole vor. Teilnehmer klicken nur auf vorgegebene Bilder.'],
                    'search'  => ['Nur Suche',   'search',  'success',
                        'Teilnehmer suchen selbst nach ARASAAC-Symbolen und fügen sie der Wolke hinzu.'],
                    'both'    => ['Beides',       'grid',    'warning',
                        'Vorgegebene Symbole und freie Suche stehen gleichzeitig zur Verfügung.'],
                ];
                foreach ($modes as $val => [$lbl, $icon, $color, $desc]):
                ?>
                <label class="d-flex align-items-start gap-3 p-3 mb-2 border rounded cursor-pointer mode-opt">
                    <input type="radio" name="mode" value="<?= $val ?>" class="mt-1 flex-shrink-0"
                           <?= ($session['mode'] === $val) ? 'checked' : '' ?>>
                    <div>
                        <div class="fw-semibold">
                            <i class="bi bi-<?= $icon ?> text-<?= $color ?> me-1"></i><?= $lbl ?>
                        </div>
                        <small class="text-muted"><?= $desc ?></small>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Symbol-Picker -->
    <div class="col-12 col-lg-7" id="symbolSection">
        <div class="card border-0 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center fw-semibold bg-white">
                <span><i class="bi bi-images me-2 text-primary"></i>Symbole festlegen</span>
                <span class="badge bg-secondary" id="symCount">0 / <?= WordCloudManager::MAX_SYMBOLS ?></span>
            </div>
            <div class="card-body">

                <!-- Suche -->
                <label class="form-label fw-semibold">ARASAAC-Symbol suchen</label>
                <div class="input-group mb-1">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="symSearch" class="form-control border-start-0 ps-0"
                           placeholder="z.B. essen, freude, schule …" autocomplete="off">
                    <select id="symLang" class="form-select" style="max-width:110px;">
                        <option value="de">🇩🇪 DE</option>
                        <option value="en">🇬🇧 EN</option>
                        <option value="es">🇪🇸 ES</option>
                        <option value="fr">🇫🇷 FR</option>
                    </select>
                </div>
                <div id="symHint" class="text-muted small mb-2">Mindestens 2 Zeichen eingeben.</div>
                <div id="symSpinner" class="text-center py-2 d-none">
                    <div class="spinner-border spinner-border-sm text-primary"></div>
                    <span class="text-muted small ms-2">Suche…</span>
                </div>
                <div id="symResults" class="sym-results mb-3"></div>

                <!-- Auswahl -->
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label fw-semibold mb-0">Ausgewählte Symbole</label>
                    <small class="text-muted">Beschriftung editierbar</small>
                </div>
                <div id="symSelected" class="sym-selected-area">
                    <div id="symEmpty" class="text-center py-4 text-muted small">
                        <i class="bi bi-images display-6 d-block mb-2 opacity-25"></i>
                        Klicken Sie auf ein Suchergebnis, um es hinzuzufügen.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
