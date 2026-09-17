<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/config/database.php';
require_once __DIR__ . '/../php/config/auth.php';
require_auth();

$user        = current_user();
$currentPage = 'productos';
$isKitchen   = strtolower((string)($user['rol'] ?? '')) === 'cocina';
$pageTitle   = 'Productos';

$success = '';
$error   = '';
$db      = database();

// Asegurar que existan las columnas de promocion en la tabla menu_items si no existen
try {
    $columns = $db->query("DESCRIBE menu_items")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('on_promo', $columns, true)) {
        $db->exec("ALTER TABLE menu_items ADD COLUMN on_promo TINYINT(1) DEFAULT 0");
    }
    if (!in_array('precio_promocion', $columns, true)) {
        $db->exec("ALTER TABLE menu_items ADD COLUMN precio_promocion DECIMAL(10,2) DEFAULT NULL");
    }
} catch (Throwable $e) {
    // Continuar si falla la comprobacion
}

// Obtener categorias reales desde la tabla categorias
$categoriesList = [];
try {
    $catQuery = $db->query("SELECT id_categoria AS id, nombre FROM categorias ORDER BY id_categoria ASC");
    $categoriesList = $catQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $categoriesList = [
        ['id' => 1, 'nombre' => 'Comidas Rapidas'],
        ['id' => 2, 'nombre' => 'Cenas'],
        ['id' => 3, 'nombre' => 'Bowls & Ensaladas'],
        ['id' => 4, 'nombre' => 'Postres'],
        ['id' => 5, 'nombre' => 'Bebidas'],
        ['id' => 6, 'nombre' => 'Desayunos'],
        ['id' => 7, 'nombre' => 'Snacks'],
    ];
}

// ID del negocio del usuario logueado
$businessId = (int)($_SESSION['business_id'] ?? 0);

// Procesar CRUD en la tabla menu_items
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'create' || $action === 'edit') {
        $id              = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $nombre          = trim((string)($_POST['nombre'] ?? ''));
        $descripcion     = trim((string)($_POST['descripcion'] ?? ''));
        $categoriaNombre = trim((string)($_POST['categoria'] ?? 'Comidas Rapidas'));
        $precio          = (int) round((float)($_POST['precio'] ?? 0));
        $descuento_pct   = (int)($_POST['descuento_pct'] ?? 0);
        $on_promo        = $descuento_pct > 0 ? 1 : 0;
        $precio_promocion = ($on_promo && $precio > 0)
            ? (int) round($precio * (1 - $descuento_pct / 100))
            : NULL;
        $stock           = (int)($_POST['stock'] ?? 0);
        $imagen_url = trim((string)($_POST['imagen_url'] ?? ''));

        // Procesar subida de imagen si se envio un archivo
        $uploadDir = realpath(__DIR__ . '/../../shizen-home/public/images/catalogo/Imagenes_prueba') . DIRECTORY_SEPARATOR;
        if (!empty($_FILES['imagen']['tmp_name']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $_FILES['imagen']['tmp_name']);
            finfo_close($finfo);
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (!in_array($mimeType, $allowed, true)) {
                $error = 'Tipo de imagen no permitido. Usa JPG, PNG, WebP o GIF.';
            } elseif ($_FILES['imagen']['size'] > 5 * 1024 * 1024) {
                $error = 'La imagen no debe superar los 5 MB.';
            } else {
                $ext      = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
                $filename = 'producto_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
                if (move_uploaded_file($_FILES['imagen']['tmp_name'], $uploadDir . $filename)) {
                    $imagen_url = '/shizen-home/public/images/catalogo/Imagenes_prueba/' . $filename;
                } else {
                    $error = 'No se pudo guardar la imagen. Verifica permisos de la carpeta.';
                }
            }
        }

        // Obtener categoria_id basado en el nombre de la categoria
        $categoria_id = NULL;
        foreach ($categoriesList as $cat) {
            if ($cat['nombre'] === $categoriaNombre) {
                $categoria_id = $cat['id'];
                break;
            }
        }

        if (mb_strlen($nombre) < 2) {
            $error = 'Ingresa un nombre valido para el producto.';
        } elseif ($precio < 1000) {
            $error = 'El precio mínimo es $1.000 COP y debe ser múltiplo de 50.';
        } else {
            try {
                if ($action === 'create') {
                    $finalImg = $imagen_url ?: 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=400';
                    $stmt = $db->prepare("
                        INSERT INTO menu_items (id_negocio, nombre, descripcion, precio, stock, imagen_url, id_categoria, on_promo, precio_promocion)
                        VALUES (:id_negocio, :nombre, :descripcion, :precio, :stock, :imagen_url, :categoria_id, :on_promo, :precio_promocion)
                    ");
                    $stmt->execute([
                        'id_negocio'       => $businessId,
                        'nombre'           => $nombre,
                        'descripcion'      => $descripcion,
                        'precio'           => $precio,
                        'stock'            => $stock,
                        'imagen_url'       => $finalImg,
                        'categoria_id'     => $categoria_id,
                        'on_promo'         => $on_promo,
                        'precio_promocion' => $precio_promocion,
                    ]);
                    $createdId = (int)$db->lastInsertId();

                    // Si la promoción está activa, se guarda en la tabla promociones
                    if ($on_promo) {
                        $pStmt = $db->prepare("
                            INSERT INTO promociones (id_negocio, id_menu_item, nombre, descripcion, imagen_url, activo)
                            VALUES (:id_negocio, :id_menu_item, :nombre, :descripcion, :imagen_url, 1)
                        ");
                        $pStmt->execute([
                            'id_negocio'   => $businessId,
                            'id_menu_item' => $createdId,
                            'nombre'       => $nombre,
                            'descripcion'  => $descripcion,
                            'imagen_url'   => $finalImg,
                        ]);
                    }

                    $success = 'Producto "' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '" guardado exitosamente en moneda colombiana (COP).';
                } else {
                    $stmt = $db->prepare("
                        UPDATE menu_items
                        SET nombre = :nombre, descripcion = :descripcion, precio = :precio, stock = :stock,
                            imagen_url = :imagen_url, id_categoria = :categoria_id,
                            on_promo = :on_promo, precio_promocion = :precio_promocion
                        WHERE id_menu_item = :id AND id_negocio = :id_negocio
                    ");
                    $stmt->execute([
                        'id'               => $id,
                        'id_negocio'       => $businessId,
                        'nombre'           => $nombre,
                        'descripcion'      => $descripcion,
                        'precio'           => $precio,
                        'stock'            => $stock,
                        'imagen_url'       => $imagen_url,
                        'categoria_id'     => $categoria_id,
                        'on_promo'         => $on_promo,
                        'precio_promocion' => $precio_promocion,
                    ]);

                    // Si la promoción está activa, se guarda o actualiza en promociones. Si ya no está en promo, sale de ella.
                    if ($on_promo) {
                        $checkP = $db->prepare("SELECT id_promocion FROM promociones WHERE id_menu_item = :id");
                        $checkP->execute(['id' => $id]);
                        if ($checkP->fetch()) {
                            $db->prepare("
                                UPDATE promociones
                                SET nombre = :nombre, descripcion = :descripcion, imagen_url = :imagen_url, activo = 1, id_negocio = :id_negocio
                                WHERE id_menu_item = :id
                            ")->execute([
                                'id'          => $id,
                                'id_negocio'  => $businessId,
                                'nombre'      => $nombre,
                                'descripcion' => $descripcion,
                                'imagen_url'  => $imagen_url,
                            ]);
                        } else {
                            $db->prepare("
                                INSERT INTO promociones (id_negocio, id_menu_item, nombre, descripcion, imagen_url, activo)
                                VALUES (:id_negocio, :id_menu_item, :nombre, :descripcion, :imagen_url, 1)
                            ")->execute([
                                'id_negocio'   => $businessId,
                                'id_menu_item' => $id,
                                'nombre'       => $nombre,
                                'descripcion'  => $descripcion,
                                'imagen_url'   => $imagen_url,
                            ]);
                        }
                    } else {
                        // Sale cuando ya no está en promo
                        $db->prepare("DELETE FROM promociones WHERE id_menu_item = :id")->execute(['id' => $id]);
                    }

                    $success = 'Producto "' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '" actualizado exitosamente en moneda colombiana (COP).';
                }
            } catch (Throwable $e) {
                $error = 'Error al guardar en menu_items: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                // Sale de promociones al eliminar el plato
                $db->prepare("DELETE FROM promociones WHERE id_menu_item = :id")->execute(['id' => $id]);
                $stmt = $db->prepare("DELETE FROM menu_items WHERE id_menu_item = :id AND id_negocio = :id_negocio");
                $stmt->execute(['id' => $id, 'id_negocio' => $businessId]);
                $success = 'Producto #' . $id . ' eliminado exitosamente de la tabla menu_items.';
            } catch (Throwable $e) {
                $error = 'Error al eliminar el producto: ' . $e->getMessage();
            }
        }
    }
}

// Consultar SOLO los productos del negocio logueado
$products = [];
try {
    $stmt = $db->prepare("
        SELECT m.*, c.nombre AS categoria
        FROM menu_items m
        LEFT JOIN categorias c ON c.id_categoria = m.id_categoria
        WHERE m.id_negocio = :id_negocio
        ORDER BY m.id_menu_item DESC
    ");
    $stmt->execute(['id_negocio' => $businessId]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = 'Error al consultar la tabla menu_items: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<?php require_once __DIR__ . '/../php/includes/head.php'; ?>
<body class="layout <?= $isKitchen ? 'kitchen-layout' : '' ?>">

<?php require_once __DIR__ . '/../php/includes/sidebar.php'; ?>

<div class="main">
  <?php require_once __DIR__ . '/../php/includes/topbar.php'; ?>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-header-title">Gestion de Productos</h1>
        <p class="page-header-sub"><?= count($products) ?> productos registrados en Pesos Colombianos (COP)</p>
      </div>
      <button class="btn btn-primary" onclick="openProductModal()">
        <i class="bx bx-plus"></i> Agregar producto
      </button>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success">
        <i class="bx bx-check-circle" style="font-size:18px;flex-shrink:0"></i>
        <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-danger">
        <i class="bx bx-error-circle" style="font-size:18px;flex-shrink:0"></i>
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <!-- Toolbar de filtros -->
    <div class="toolbar">
      <div class="search-wrap">
        <i class="bx bx-search"></i>
        <input type="text" class="search-input" placeholder="Buscar por nombre o descripcion..."
               oninput="filterProducts(this.value)">
      </div>
      <select class="form-control" style="width:auto;padding:9px 14px" onchange="filterCategory(this.value)">
        <option value="">Todas las categorias</option>
        <?php foreach ($categoriesList as $cat): ?>
          <option value="<?= htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
      <select class="form-control" style="width:auto;padding:9px 14px" onchange="filterPromo(this.value)">
        <option value="">Todos los productos</option>
        <option value="promo">En Promocion 🔥</option>
        <option value="normal">Sin Promocion</option>
      </select>
    </div>

    <!-- Grid de productos -->
    <div class="products-grid" id="productsGrid">
      <?php foreach ($products as $p): ?>
        <?php
          $hasPromo = !empty($p['on_promo']);
          $rawImg   = $p['imagen_url'] ?? '';
          // Convertir rutas relativas de Imagenes_prueba a la URL publica correcta
          if (str_contains($rawImg, 'Imagenes_prueba/')) {
              $file   = basename($rawImg);
              $imgUrl = '/shizen-home/public/images/catalogo/Imagenes_prueba/' . $file;
          } elseif (!empty($rawImg)) {
              $imgUrl = $rawImg;
          } else {
              $imgUrl = 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=400';
          }
        ?>
        <div class="product-card"
             data-name="<?= strtolower(htmlspecialchars(($p['nombre'] ?? '') . ' ' . ($p['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8')) ?>"
             data-cat="<?= htmlspecialchars($p['categoria'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
             data-promo="<?= $hasPromo ? 'promo' : 'normal' ?>">
          
          <div class="product-card-img" style="position:relative;overflow:hidden;background:#f3f4f6;height:160px">
            <img src="<?= htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8') ?>"
                 alt="<?= htmlspecialchars($p['nombre'] ?? 'Producto', ENT_QUOTES, 'UTF-8') ?>"
                 style="width:100%;height:100%;object-fit:cover"
                 onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=400'">
            <?php if ($hasPromo): ?>
              <span class="badge badge-orange" style="position:absolute;top:10px;right:10px;box-shadow:0 2px 6px rgba(249,115,22,.5)">
                🔥 PROMOCION
              </span>
            <?php endif; ?>
          </div>

          <div class="product-card-body">
            <div class="product-card-name"><?= htmlspecialchars($p['nombre'] ?? 'Sin nombre', ENT_QUOTES, 'UTF-8') ?></div>
            <div class="product-card-cat"><?= htmlspecialchars($p['categoria'] ?? 'Sin categoria', ENT_QUOTES, 'UTF-8') ?></div>
            
            <?php if (!empty($p['descripcion'])): ?>
              <div style="font-size:12px;color:#6b7280;margin-bottom:8px;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">
                <?= htmlspecialchars($p['descripcion'], ENT_QUOTES, 'UTF-8') ?>
              </div>
            <?php endif; ?>

            <div style="margin-bottom:12px;display:flex;gap:4px;flex-wrap:wrap;align-items:center">
              <?php if ((int)$p['stock'] === 0): ?>
                <span class="badge badge-red">Sin stock</span>
              <?php elseif ((int)$p['stock'] < 10): ?>
                <span class="badge badge-yellow">Stock bajo (<?= $p['stock'] ?>)</span>
              <?php else: ?>
                <span class="badge badge-green">Stock (<?= $p['stock'] ?>)</span>
              <?php endif; ?>
            </div>

            <div class="product-card-footer" style="gap:4px">
              <div>
                <?php if ($hasPromo && !empty($p['precio_promocion'])): ?>
                  <span class="product-price" style="color:#ea580c;font-size:15px"><?= format_cop($p['precio_promocion']) ?></span>
                  <span style="font-size:11px;color:#9ca3af;text-decoration:line-through;display:block"><?= format_cop($p['precio']) ?></span>
                <?php else: ?>
                  <span class="product-price" style="font-size:15px"><?= format_cop($p['precio']) ?></span>
                <?php endif; ?>
              </div>

              <div style="display:flex;gap:4px">

                <!-- Boton Editar -->
                <button class="btn btn-secondary btn-sm" style="padding:6px 8px"
                        onclick='editProduct(<?= json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                  <i class="bx bx-edit"></i>
                </button>

                <!-- Boton Eliminar -->
                <form method="post" onsubmit="return confirm('¿Eliminar producto <?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?>?')" style="display:inline">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $p['id_menu_item'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm" style="padding:6px 8px">
                    <i class="bx bx-trash"></i>
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </main>
</div>

<!-- Modal para Agregar / Editar -->
<div id="productModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;padding:20px">
  <div class="card" style="width:100%;max-width:540px;max-height:90vh;overflow-y:auto">
    <div class="card-header">
      <div class="card-title" id="modalTitle">Agregar Producto</div>
      <button type="button" onclick="closeProductModal()" class="btn btn-secondary btn-sm" style="padding:4px 8px">✕</button>
    </div>
    <div class="card-body">
      <form method="post" id="productForm" enctype="multipart/form-data">
        <input type="hidden" name="action" id="formAction" value="create">
        <input type="hidden" name="id" id="productId" value="0">

        <div class="form-group">
          <label class="form-label" for="prodNombre">Nombre del producto *</label>
          <input type="text" id="prodNombre" name="nombre" class="form-control" placeholder="Ej: Hamburguesa Vegana" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="prodDescripcion">Descripcion</label>
          <textarea id="prodDescripcion" name="descripcion" class="form-control" rows="2" placeholder="Descripcion del plato o ingrediente..."></textarea>
        </div>

        <div class="form-group">
          <label class="form-label" for="prodCategory">Categoria *</label>
          <select id="prodCategory" name="categoria" class="form-control" required>
            <?php foreach ($categoriesList as $cat): ?>
              <option value="<?= htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label" for="prodPrecio">Precio Normal (COP $) *</label>
            <input type="number" id="prodPrecio" name="precio" class="form-control" placeholder="18500" required min="1000" step="50" title="Mínimo $1.000 COP, en múltiplos de 50" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
          </div>
          <div class="form-group">
            <label class="form-label" for="prodStock">Stock disponible *</label>
            <input type="number" id="prodStock" name="stock" class="form-control" value="10" required min="0">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Imagen del producto</label>
          <!-- Campo oculto para conservar la URL actual al editar sin cambiar imagen -->
          <input type="hidden" id="prodImagenUrl" name="imagen_url">
          <!-- Preview de imagen actual -->
          <div id="imgPreviewWrap" style="display:none;margin-bottom:10px;text-align:center">
            <img id="imgPreview" src="" alt="Preview" style="max-width:100%;max-height:160px;border-radius:10px;object-fit:cover;border:2px solid #e5e7eb">
          </div>
          <label for="prodImagen" style="display:flex;align-items:center;gap:10px;cursor:pointer;border:2px dashed #d1d5db;border-radius:10px;padding:14px 16px;background:#f9fafb;transition:border-color .2s" onmouseover="this.style.borderColor='#22c55e'" onmouseout="this.style.borderColor='#d1d5db'">
            <i class="bx bx-image-add" style="font-size:24px;color:#6b7280"></i>
            <div>
              <div style="font-weight:600;color:#374151;font-size:13px">Subir imagen</div>
              <div style="font-size:11px;color:#9ca3af">JPG, PNG, WebP o GIF · Máx. 5 MB</div>
            </div>
          </label>
          <input type="file" id="prodImagen" name="imagen" accept="image/*" style="display:none" onchange="previewImg(this)">
        </div>

        <!-- Seccion de Promocion -->
        <div style="background:#fff7ed;padding:14px;border-radius:10px;border:1px solid #ffedd5;margin-bottom:20px">
          <div style="font-weight:700;color:#ea580c;margin-bottom:8px">🔥 Promocion / Descuento</div>
          <div style="font-size:12px;color:#6b7280;margin-bottom:12px">Selecciona el porcentaje de descuento. El precio promocional se calcula automaticamente.</div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label" for="prodDescuentoPct" style="color:#ea580c;font-weight:600">Porcentaje de descuento</label>
            <select id="prodDescuentoPct" name="descuento_pct" class="form-control" onchange="calcPromoPrice()">
              <option value="0">Sin descuento (precio normal)</option>
              <option value="10">10% de descuento</option>
              <option value="15">15% de descuento</option>
              <option value="20">20% de descuento</option>
              <option value="25">25% de descuento</option>
              <option value="30">30% de descuento</option>
              <option value="40">40% de descuento</option>
              <option value="50">50% de descuento</option>
            </select>
          </div>
          <div id="promoPricePreview" style="display:none;margin-top:10px;padding:8px 12px;background:#ea580c;color:#fff;border-radius:8px;font-size:13px;font-weight:600"></div>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px">
          <button type="button" onclick="closeProductModal()" class="btn btn-secondary">Cancelar</button>
          <button type="submit" class="btn btn-primary">
            <i class="bx bx-save"></i> Guardar Producto
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function formatCOP(n) {
  return '$ ' + Math.round(n).toLocaleString('es-CO');
}

function calcPromoPrice() {
  const precio = parseFloat(document.getElementById('prodPrecio').value) || 0;
  const pct    = parseInt(document.getElementById('prodDescuentoPct').value, 10) || 0;
  const preview = document.getElementById('promoPricePreview');
  if (pct > 0 && precio > 0) {
    const promoPrice = Math.round(precio * (1 - pct / 100));
    preview.style.display = 'block';
    preview.textContent = '🔥 Precio promocional: ' + formatCOP(promoPrice) + ' (antes ' + formatCOP(precio) + ')';
  } else {
    preview.style.display = 'none';
    preview.textContent = '';
  }
}

function openProductModal() {
  document.getElementById('formAction').value = 'create';
  document.getElementById('productId').value = '0';
  document.getElementById('modalTitle').innerText = 'Agregar Producto (Pesos Colombianos - COP)';
  document.getElementById('productForm').reset();
  document.getElementById('prodDescuentoPct').value = '0';
  document.getElementById('promoPricePreview').style.display = 'none';
  document.getElementById('prodImagenUrl').value = '';
  document.getElementById('imgPreviewWrap').style.display = 'none';
  document.getElementById('imgPreview').src = '';
  document.getElementById('productModal').style.display = 'flex';
}

function closeProductModal() {
  document.getElementById('productModal').style.display = 'none';
}

function previewImg(input) {
  const wrap = document.getElementById('imgPreviewWrap');
  const img  = document.getElementById('imgPreview');
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      img.src = e.target.result;
      wrap.style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
    // Limpiar la URL guardada: la imagen nueva viene por FILE, no por campo oculto
    document.getElementById('prodImagenUrl').value = '';
  }
}

function setImgPreview(url) {
  const wrap = document.getElementById('imgPreviewWrap');
  const img  = document.getElementById('imgPreview');
  if (url) {
    img.src = url;
    wrap.style.display = 'block';
  } else {
    wrap.style.display = 'none';
    img.src = '';
  }
}

function editProduct(p) {
  document.getElementById('formAction').value = 'edit';
  document.getElementById('productId').value = p.id_menu_item;
  document.getElementById('modalTitle').innerText = 'Editar Producto #' + p.id_menu_item;
  document.getElementById('prodNombre').value = p.nombre || '';
  document.getElementById('prodDescripcion').value = p.descripcion || '';
  document.getElementById('prodCategory').value = p.categoria || 'Comidas Rapidas';
  document.getElementById('prodPrecio').value = p.precio || '';
  document.getElementById('prodStock').value = p.stock || 0;
  document.getElementById('prodImagenUrl').value = p.imagen_url || '';
  // Mostrar imagen actual como preview
  setImgPreview(p.imagen_url || '');
  // Limpiar file input por si estaba seleccionado antes
  document.getElementById('prodImagen').value = '';

  // Determinar el descuento actual a partir del precio_promocion existente
  const precio = parseFloat(p.precio) || 0;
  const promoPrecio = parseFloat(p.precio_promocion) || 0;
  let pctGuess = 0;
  if (p.on_promo && precio > 0 && promoPrecio > 0 && promoPrecio < precio) {
    const raw = Math.round((1 - promoPrecio / precio) * 100);
    const allowed = [10, 15, 20, 25, 30, 40, 50];
    pctGuess = allowed.reduce((prev, cur) => Math.abs(cur - raw) < Math.abs(prev - raw) ? cur : prev, 0);
  }
  document.getElementById('prodDescuentoPct').value = String(pctGuess);
  calcPromoPrice();
  document.getElementById('productModal').style.display = 'flex';
}

function filterProducts(q) {
  document.querySelectorAll('.product-card').forEach(c => {
    c.style.display = c.dataset.name.includes(q.toLowerCase()) ? '' : 'none';
  });
}

function filterCategory(cat) {
  document.querySelectorAll('.product-card').forEach(c => {
    c.style.display = (!cat || c.dataset.cat === cat) ? '' : 'none';
  });
}

function filterPromo(promo) {
  document.querySelectorAll('.product-card').forEach(c => {
    c.style.display = (!promo || c.dataset.promo === promo) ? '' : 'none';
  });
}

// Recalcular al cambiar el precio normal tambien
document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('prodPrecio').addEventListener('input', calcPromoPrice);
});
</script>
</body>
</html>
