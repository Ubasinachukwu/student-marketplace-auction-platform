<?php
// marketplace/admin/categories.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login and admin role
Session::requireRole('admin');

$db = db();
$isAdminPage = true;

// Handle category actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!Session::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setError('Invalid security token');
        redirect(SITE_URL . '/admin/categories.php');
    }

    $action = isset($_POST['action']) ? sanitize($_POST['action']) : '';

    try {
        $db->beginTransaction();

        if ($action === 'add') {
            $name = sanitize($_POST['name'] ?? '');
            $parentId = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;
            $description = sanitize($_POST['description'] ?? '');
            $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;

            if (empty($name)) {
                throw new Exception('Category name is required');
            }

            $slug = slugify($name);
            
            // Check for duplicate slug
            $existing = $db->fetchOne(
                "SELECT id FROM categories WHERE slug = ?",
                [$slug]
            );
            if ($existing) {
                throw new Exception('Category with this name already exists');
            }

            $db->insert(
                "INSERT INTO categories (name, slug, parent_id, description, sort_order, is_active) 
                 VALUES (?, ?, ?, ?, ?, 1)",
                [$name, $slug, $parentId ?: null, $description, $sortOrder]
            );

            $message = "Category '{$name}' added successfully";

        } elseif ($action === 'edit') {
            $id = (int)$_POST['id'];
            $name = sanitize($_POST['name'] ?? '');
            $parentId = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;
            $description = sanitize($_POST['description'] ?? '');
            $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if (empty($name)) {
                throw new Exception('Category name is required');
            }

            $slug = slugify($name);
            
            // Check for duplicate slug (excluding current)
            $existing = $db->fetchOne(
                "SELECT id FROM categories WHERE slug = ? AND id != ?",
                [$slug, $id]
            );
            if ($existing) {
                throw new Exception('Category with this name already exists');
            }

            // Prevent category from being its own parent
            if ($parentId == $id) {
                throw new Exception('Category cannot be its own parent');
            }

            $db->update(
                "UPDATE categories SET 
                    name = ?, 
                    slug = ?, 
                    parent_id = ?, 
                    description = ?, 
                    sort_order = ?,
                    is_active = ?
                 WHERE id = ?",
                [$name, $slug, $parentId ?: null, $description, $sortOrder, $isActive, $id]
            );

            $message = "Category '{$name}' updated successfully";

        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            
            // Check if category has subcategories
            $subCount = $db->fetchOne(
                "SELECT COUNT(*) as count FROM categories WHERE parent_id = ?",
                [$id]
            );
            if ($subCount['count'] > 0) {
                throw new Exception('Cannot delete category with subcategories. Delete subcategories first.');
            }

            // Check if category is used in listings
            $listingCount = $db->fetchOne(
                "SELECT COUNT(*) as count FROM listings WHERE category_id = ?",
                [$id]
            );
            if ($listingCount['count'] > 0) {
                throw new Exception('Cannot delete category with listings. Reassign listings first.');
            }

            $category = $db->fetchOne("SELECT name FROM categories WHERE id = ?", [$id]);
            $db->delete("DELETE FROM categories WHERE id = ?", [$id]);
            $message = "Category '{$category['name']}' deleted successfully";
        }

        // Log admin action
        $db->insert(
            "INSERT INTO admin_logs (admin_id, action, target_type, target_id, new_value) 
             VALUES (?, ?, 'category', ?, ?)",
            [Session::getUserId(), $action, $_POST['id'] ?? 0, $message]
        );

        $db->commit();
        setSuccess($message);

    } catch (Exception $e) {
        $db->rollback();
        setError($e->getMessage());
    }

    redirect(SITE_URL . '/admin/categories.php');
}

// Get all categories
$categories = $db->fetchAll(
    "SELECT c.*, 
            (SELECT COUNT(*) FROM listings WHERE category_id = c.id) as listing_count,
            (SELECT COUNT(*) FROM categories WHERE parent_id = c.id) as sub_count,
            p.name as parent_name
     FROM categories c
     LEFT JOIN categories p ON c.parent_id = p.id
     ORDER BY c.parent_id IS NULL DESC, c.parent_id, c.sort_order, c.name"
);

// Get parent categories for dropdown
$parentCategories = $db->fetchAll(
    "SELECT * FROM categories WHERE parent_id IS NULL ORDER BY sort_order, name"
);

$csrfToken = Session::generateCSRFToken();
$pageJS = 'admin.js';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">

        <!-- ============================================
             SIDEBAR - Using centralized include file
        ============================================= -->
        <?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>


        <!-- ============================================
             MAIN CONTENT
        ============================================= -->
        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="bi bi-tags"></i> Categories</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal">
                    <i class="bi bi-plus-circle"></i> Add Category
                </button>
            </div>

            <!-- Categories Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Parent</th>
                                    <th>Slug</th>
                                    <th>Listings</th>
                                    <th>Subcategories</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($categories)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="bi bi-tags display-4 text-muted d-block"></i>
                                            <p class="text-muted">No categories found</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($categories as $cat): ?>
                                        <tr class="<?php echo $cat['parent_id'] ? 'table-light' : ''; ?>">
                                            <td>
                                                <?php if ($cat['parent_id']): ?>
                                                    <span class="text-muted me-2">└─</span>
                                                <?php endif; ?>
                                                <i class="bi <?php echo getCategoryIcon($cat['slug']); ?> text-primary me-1"></i>
                                                <strong><?php echo htmlspecialchars($cat['name']); ?></strong>
                                                <?php if ($cat['sub_count'] > 0): ?>
                                                    <span class="badge bg-info ms-1">Parent</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($cat['parent_name'] ?? '—'); ?>
                                            </td>
                                            <td>
                                                <code><?php echo htmlspecialchars($cat['slug']); ?></code>
                                            </td>
                                            <td>
                                                <?php echo $cat['listing_count']; ?>
                                            </td>
                                            <td>
                                                <?php echo $cat['sub_count']; ?>
                                            </td>
                                            <td>
                                                <?php if ($cat['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary edit-category" 
                                                            data-id="<?php echo $cat['id']; ?>"
                                                            data-name="<?php echo htmlspecialchars($cat['name']); ?>"
                                                            data-parent="<?php echo $cat['parent_id'] ?? 0; ?>"
                                                            data-description="<?php echo htmlspecialchars($cat['description'] ?? ''); ?>"
                                                            data-sort="<?php echo $cat['sort_order']; ?>"
                                                            data-active="<?php echo $cat['is_active']; ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <?php if ($cat['listing_count'] == 0 && $cat['sub_count'] == 0): ?>
                                                        <button class="btn btn-outline-danger delete-category" 
                                                                data-id="<?php echo $cat['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($cat['name']); ?>">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?php echo SITE_URL; ?>/admin/categories.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="categoryModalTitle">
                        <i class="bi bi-tag"></i> Add Category
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="categoryId" value="0">
                    
                    <div class="mb-3">
                        <label for="categoryName" class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="categoryName" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="categoryParent" class="form-label">Parent Category</label>
                        <select class="form-select" id="categoryParent" name="parent_id">
                            <option value="0">None (Top Level)</option>
                            <?php foreach ($parentCategories as $parent): ?>
                                <option value="<?php echo $parent['id']; ?>">
                                    <?php echo htmlspecialchars($parent['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="categoryDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="categoryDescription" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="categorySort" class="form-label">Sort Order</label>
                        <input type="number" class="form-control" id="categorySort" name="sort_order" value="0">
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="categoryActive" name="is_active" checked>
                            <label class="form-check-label" for="categoryActive">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Edit category
    document.querySelectorAll('.edit-category').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('formAction').value = 'edit';
            document.getElementById('categoryId').value = this.dataset.id;
            document.getElementById('categoryName').value = this.dataset.name;
            document.getElementById('categoryParent').value = this.dataset.parent;
            document.getElementById('categoryDescription').value = this.dataset.description;
            document.getElementById('categorySort').value = this.dataset.sort;
            document.getElementById('categoryActive').checked = this.dataset.active == 1;
            document.getElementById('categoryModalTitle').innerHTML = '<i class="bi bi-pencil"></i> Edit Category';
            
            new bootstrap.Modal(document.getElementById('categoryModal')).show();
        });
    });

    // Delete category
    document.querySelectorAll('.delete-category').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            
            if (confirm(`Are you sure you want to delete "${name}"? This cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
    });

    // Reset modal on hide
    document.getElementById('categoryModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('formAction').value = 'add';
        document.getElementById('categoryId').value = '0';
        document.getElementById('categoryName').value = '';
        document.getElementById('categoryParent').value = '0';
        document.getElementById('categoryDescription').value = '';
        document.getElementById('categorySort').value = '0';
        document.getElementById('categoryActive').checked = true;
        document.getElementById('categoryModalTitle').innerHTML = '<i class="bi bi-tag"></i> Add Category';
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>