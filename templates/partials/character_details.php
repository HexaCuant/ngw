<?php
/**
 * Character details partial template
 * 
 * Required variables:
 * @var array $activeCharacter - Character data
 * @var array $genes - Character's genes
 * @var array $connections - Character's connections
 * @var \Ngw\Auth\SessionManager $session
 * @var int $userId - Current user ID
 * @var \Ngw\Models\Character $characterModel
 * 
 * Optional variables:
 * @var array|null $activeGene - Currently open gene (if any)
 * @var array $alleles - Alleles of the active gene
 * @var bool $showConnections - Whether to show connections panel
 */

$activeCharacterId = $activeCharacter['id'] ?? 0;
$numSubstrates = (int)($activeCharacter['substrates'] ?? 0);
$showConnections = $showConnections ?? false;
$activeGene = $activeGene ?? null;
$alleles = $alleles ?? [];
$isOwner = (int)$activeCharacter['creator_id'] === $userId;
$canEdit = $session->isTeacher() || $session->isAdmin() || $isOwner;
// Students can only see details if character is visible (or they own it)
$canViewDetails = $canEdit || (int)$activeCharacter['is_visible'] === 1;
?>
<div class="card" data-active-character-id="<?= e($activeCharacterId) ?>">
    <h3>Detalles del Carácter: <?= e($activeCharacter['name']) ?></h3>
    
    <?php if (!$canViewDetails) : ?>
    <!-- Character not visible to students -->
    <div class="alert alert-info" style="margin: 1rem 0;">
        <p>Este carácter no está visible. El profesor no ha habilitado la visualización de sus genes y conexiones.</p>
    </div>
    <div style="margin-bottom: 1.5rem;">
        <button type="button" onclick="closeCharacter()" class="btn-secondary">Cerrar carácter</button>
    </div>
    <?php else : ?>
    
    <!-- Propiedades del carácter editable para profesores/admins -->
    <?php if ($session->isTeacher() || $session->isAdmin()) : ?>
    <div style="margin-bottom: 1rem;">
        <div style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
            <label style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="checkbox" id="char-visible" <?= (int)$activeCharacter['is_visible'] === 1 ? 'checked' : '' ?>>
                <span>Visible</span>
            </label>
            <label style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="checkbox" id="char-public" <?= (int)$activeCharacter['is_public'] === 1 ? 'checked' : '' ?>>
                <span>Público</span>
            </label>
            <button type="button" onclick="updateCharacterProps(<?= $activeCharacter['id'] ?>, document.getElementById('char-visible').checked ? 1 : 0, document.getElementById('char-public').checked ? 1 : 0)" class="btn-success">Guardar cambios</button>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Botones principales -->
    <div style="margin-bottom: 1.5rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <button type="button" onclick="closeCharacter()" class="btn-secondary">Cerrar carácter</button>
        
        <button type="button" class="btn-primary" id="toggle-genes-btn" onclick="toggleGenesView()">
            Ver Genes
        </button>
        
        <button type="button" class="btn-primary" id="toggle-connections-btn" onclick="toggleConnectionsView()">
            Ver Conexiones
        </button>
        
        <?php if ($canEdit) : ?>
            <button type="button" class="btn-success" onclick="document.getElementById('create-gene-form-container').style.display = document.getElementById('create-gene-form-container').style.display === 'none' ? 'block' : 'none';">
                Crear nuevo gen
            </button>
        <?php endif; ?>
    </div>
    
    <!-- Tabla de Genes (oculta por defecto) -->
    <div id="genes-view" style="display: none; margin-top: 1.5rem;">
        <?php if (!empty($genes)) : ?>
            <h4>Genes del carácter</h4>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Cromosoma</th>
                        <th>Posición</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($genes as $gene) : ?>
                        <tr>
                            <td><?= e($gene['id']) ?></td>
                            <td><?= e($gene['name']) ?></td>
                            <td><?php 
                                $chrDisplay = '';
                                if (!empty($gene['chromosome'])) {
                                    $chrDisplay = $gene['chromosome'];
                                    if (!empty($gene['code'])) {
                                        $chrDisplay .= ' (' . $gene['code'] . ')';
                                    }
                                } elseif (!empty($gene['code'])) {
                                    $chrDisplay = $gene['code'];
                                }
                                echo e($chrDisplay);
                            ?></td>
                            <td><?= e($gene['position']) ?></td>
                            <td>
                                <button type="button" onclick="openGene(<?= e($gene['id']) ?>)" class="btn-primary btn-small">Abrir</button>
                                <?php if ($canEdit) : ?>
                                    <button type="button" onclick="deleteGene(<?= e($gene['id']) ?>, '<?= e(addslashes($gene['name'])) ?>')" class="btn-danger btn-small">Borrar</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p class="text-center">No hay genes definidos para este carácter.</p>
        <?php endif; ?>
    </div>

    <!-- Formulario de crear gen (oculto por defecto) -->
    <?php if ($canEdit) : ?>
        <div id="create-gene-form-container" style="display: none; margin-top: 1.5rem;">
            <h4>Crear nuevo gen</h4>
            <form method="post" id="create-gene-form">
                <input type="hidden" name="char_action" value="create_gene">
                <div class="form-group">
                    <label>Nombre</label>
                    <input type="text" name="gene_name" required>
                </div>
                <div class="form-group">
                    <label>Cromosoma nº</label>
                    <input type="text" name="gene_chr" placeholder="Ej: 1, 2, 3...">
                </div>
                <div class="form-group">
                    <label>Tipo de cromosoma</label>
                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <label><input type="checkbox" name="gene_type[]" value="X"> X</label>
                        <label><input type="checkbox" name="gene_type[]" value="Y"> Y</label>
                        <label><input type="checkbox" name="gene_type[]" value="A" checked> A</label>
                        <label><input type="checkbox" name="gene_type[]" value="B" checked> B</label>
                    </div>
                </div>
                <div class="form-group">
                    <label>Posición</label>
                    <input type="text" name="gene_pos">
                </div>
                <button type="submit" class="btn-success btn-small">Crear Gen</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Vista de alelos de un gen abierto -->
    <?php if ($activeGene) : ?>
        <div style="margin-top: 1.5rem;">
            <h4>Gen abierto: <?= e($activeGene['name']) ?></h4>

            <div style="margin: .5rem 0 1rem 0;">
                <button type="button" onclick="closeGene()" class="btn-secondary btn-small">Cerrar Gen</button>
            </div>

            <table style="width: 100%;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Valor</th>
                        <th>Aditivo</th>
                        <th>Dominancia</th>
                        <th>Epistasis</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($alleles)) : ?>
                        <?php foreach ($alleles as $al) : ?>
                        <?php 
                            $displayDominance = $al['dominance'];
                            if ((int)$al['additive'] === 1 && $displayDominance !== null) {
                                $strDom = (string)$displayDominance;
                                if (strpos($strDom, '1') === 0) {
                                    $displayDominance = substr($strDom, 1);
                                }
                            }
                        ?>
                        <tr>
                            <td><?= e($al['id']) ?></td>
                            <td><?= e($al['name']) ?></td>
                            <td><?= e($al['value']) ?></td>
                            <td><?= e((int)$al['additive'] === 1 ? 'Sí' : 'No') ?></td>
                            <td><?= e($displayDominance) ?></td>
                            <td><?= e($al['epistasis']) ?></td>
                            <td>
                                <?php if ($canEdit) : ?>
                                    <button onclick="deleteAllele(<?= $al['id'] ?>, () => openGene(<?= (int)$activeGene['id'] ?>, true))" class="btn-danger btn-small">Eliminar</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="7" class="text-center">No hay alelos definidos</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($canEdit) : ?>
                <form method="post" id="add-allele-form" style="margin-top: 1rem;">
                    <input type="hidden" name="char_action" value="add_allele">
                    <h5>Añadir nuevo alelo</h5>
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="allele_name" required>
                    </div>
                    <div class="form-group">
                        <label>Valor</label>
                        <input type="text" name="allele_value">
                    </div>
                    <div class="form-group">
                        <label>Aditivo</label>
                        <label><input type="checkbox" name="allele_additive" value="1"> Sí</label>
                    </div>
                    <div class="form-group">
                        <label>Dominancia</label>
                        <input type="text" name="allele_dominance">
                    </div>
                    <div class="form-group">
                        <label>Epistasis</label>
                        <input type="text" name="allele_epistasis">
                    </div>
                    <button type="submit" class="btn-success btn-small">Añadir Alelo</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Sección de Conexiones -->
<a name="connections"></a>
<div id="connections-view" style="display: none; margin-top: 1.5rem;" class="card">
    <h4>Conexiones del Carácter</h4>
    
    <!-- Warning cuando no hay genes (visible solo si no hay genes) -->
    <div id="no-genes-warning" class="alert" style="background: var(--color-warning-bg, #fff3cd); border: 1px solid var(--color-warning, #ffc107); border-radius: 4px; padding: 1rem; margin: 1rem 0; text-align: center; color: var(--color-warning-text, #856404); <?= !empty($genes) ? 'display: none;' : '' ?>">
        <strong>⚠️ Primero debes crear genes para este carácter.</strong>
    </div>
    
    <!-- Contenido de conexiones (oculto si no hay genes) -->
    <div id="connections-content" style="<?= empty($genes) ? 'display: none;' : '' ?>">
        <!-- Mostrar conexiones existentes -->
        <?php if (!empty($connections)) : ?>
            <table id="connections-table" style="width: 100%; margin-bottom: 1rem;">
                <thead>
                    <tr>
                        <th>Estado A</th>
                        <th>Gen (Transición)</th>
                        <th>Estado B</th>
                        <?php if ($canEdit) : ?>
                            <th>Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($connections as $conn) : ?>
                        <?php
                            $transGene = $characterModel->getGeneById((int)$conn['transition']);
                            $geneName = $transGene ? $transGene['name'] : 'Gen #' . $conn['transition'];
                        ?>
                        <tr data-connection-id="<?= e($conn['id']) ?>">
                            <td>S<?= e($conn['state_a']) ?></td>
                            <td><?= e($geneName) ?></td>
                            <td>S<?= e($conn['state_b']) ?></td>
                            <?php if ($canEdit) : ?>
                                <td>
                                    <button onclick="deleteConnectionRow(this, <?= e($conn['id']) ?>)" class="btn-danger btn-small">Borrar</button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p class="text-center">No hay conexiones definidas para este carácter.</p>
        <?php endif; ?>

        <!-- Visualización de red de Petri -->
        <div style="border-top: 1px solid var(--color-border); padding-top: 1rem; margin-top: 1rem;">
            <h5>Diagrama de Red de Petri</h5>
            <div id="petri-net-diagram" style="width: 100%; min-height: 300px; border: 1px solid var(--color-border); border-radius: 4px; padding: 1rem; background: #fafafa; overflow-x: auto;">
                <?php if (empty($connections)) : ?>
                    <p id="petri-net-placeholder" class="text-center" style="color: var(--color-text-secondary);">No hay conexiones definidas para este carácter.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Formulario para añadir conexiones -->
        <?php if ($canEdit) : ?>
            <div style="border-top: 1px solid var(--color-border); padding-top: 1rem; margin-top: 1rem;">
                <h5>Añadir nueva conexión</h5>
                
                <!-- Warning cuando no hay genes (dentro del formulario) -->
                <div id="no-genes-warning-form" class="alert" style="background: var(--color-warning-bg, #fff3cd); border: 1px solid var(--color-warning, #ffc107); border-radius: 4px; padding: 1rem; margin: 1rem 0; text-align: center; color: var(--color-warning-text, #856404); <?= !empty($genes) ? 'display: none;' : '' ?>">
                    <strong>⚠️ Primero debes crear genes para este carácter.</strong>
                </div>
            
            <!-- Contenedor del formulario de conexiones (oculto si no hay genes) -->
            <div id="connection-form-container" style="<?= empty($genes) ? 'display: none;' : '' ?>">
                <!-- Update substrates form -->
                <form method="post" id="substrates-form" style="margin-bottom: 1rem; display:flex; align-items:center; gap:0.5rem;">
                    <input type="hidden" name="char_action" value="update_substrates">
                    <div class="form-group" style="margin:0;">
                        <label style="display:flex; align-items:center; gap:0.5rem;">Número de sustratos: 
                            <input type="number" 
                                   id="substrates-input" 
                                   name="substrates" 
                                   value="<?= e($numSubstrates) ?>" 
                                   min="0" 
                                   max="20"
                                   style="width: 80px;">
                        </label>
                        <small style="color: var(--color-text-secondary); margin-left: 0.5rem;">
                            (Se actualiza automáticamente)
                        </small>
                    </div>
                </form>

                <!-- Formulario de conexiones -->
                <form method="post" id="add-connection-form">
                    <input type="hidden" name="char_action" value="add_connection">
                    
                    <div class="form-group" id="state-a-group">
                        <label>Estado inicial (S):</label>
                        <div id="state-a-container" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <?php if ($numSubstrates > 0) : ?>
                            <?php for ($i = 0; $i < $numSubstrates; $i++) : ?>
                                <label>
                                    <input type="radio" name="state_a" value="<?= $i ?>" required> S<?= $i ?>
                                </label>
                            <?php endfor; ?>
                        <?php else : ?>
                            <span id="no-substrates-a" class="text-muted" style="color: var(--color-text-muted); font-style: italic;">Sin sustratos definidos</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label>Gen (Transición):</label>
                    <div id="transition-container" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <?php foreach ($genes as $gene) : ?>
                            <label>
                                <input type="radio" name="transition" value="<?= e($gene['id']) ?>" required> <?= e($gene['name']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group" id="state-b-group">
                    <label>Estado final (S):</label>
                    <div id="state-b-container" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <?php if ($numSubstrates > 0) : ?>
                            <?php for ($i = 0; $i < $numSubstrates; $i++) : ?>
                                <label>
                                    <input type="radio" name="state_b" value="<?= $i ?>" required> S<?= $i ?>
                                </label>
                            <?php endfor; ?>
                        <?php else : ?>
                            <span id="no-substrates-b" class="text-muted" style="color: var(--color-text-muted); font-style: italic;">Sin sustratos definidos</span>
                        <?php endif; ?>
                    </div>
                </div>

                <button type="submit" class="btn-success" id="save-connection-btn" style="<?= $numSubstrates > 0 ? '' : 'display: none;' ?>">Guardar Conexión</button>
            </form>
            </div><!-- End connection-form-container -->
            </div><!-- End add connection section -->
        <?php endif; ?>
    </div><!-- End connections-content -->

<?php endif; // End of $canViewDetails check ?>
</div>
