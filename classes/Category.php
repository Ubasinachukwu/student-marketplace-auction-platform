<?php
// marketplace/classes/Category.php

// =============================================
// FIX: Use config/database.php instead of missing Database.php
// =============================================
require_once __DIR__ . '/../config/database.php';

class Category {
    private $db;
    private $id;
    private $name;
    private $slug;
    private $parentId;
    private $description;
    private $icon;
    private $sortOrder;
    private $isActive;
    private $createdAt;
    private $updatedAt;
    private $subcategories = [];
    private $parent = null;
    private $listingCount = 0;

    public function __construct($categoryId = null) {
        // =============================================
        // FIX: Use the existing db() function from config/database.php
        // =============================================
        global $db;
        $this->db = $db ?? db();
        
        if ($categoryId) {
            $this->load($categoryId);
        }
    }

    /**
     * Load category from database
     */
    public function load($categoryId) {
        $category = $this->db->fetchOne(
            "SELECT * FROM categories WHERE id = ?",
            [$categoryId]
        );
        
        if ($category) {
            $this->id = $category['id'];
            $this->name = $category['name'];
            $this->slug = $category['slug'];
            $this->parentId = $category['parent_id'];
            $this->description = $category['description'];
            $this->icon = $category['icon'];
            $this->sortOrder = $category['sort_order'];
            $this->isActive = (bool)$category['is_active'];
            $this->createdAt = $category['created_at'];
            $this->updatedAt = $category['updated_at'];
            
            // Load parent if exists
            if ($this->parentId) {
                $this->parent = new Category($this->parentId);
            }
            
            // Load subcategories
            $this->loadSubcategories();
            
            // Get listing count
            $this->listingCount = $this->getListingCount();
            
            return true;
        }
        return false;
    }

    /**
     * Create new category
     */
    public function create($data) {
        $required = ['name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        $this->name = $data['name'];
        $this->slug = slugify($data['name']);
        $this->parentId = $data['parent_id'] ?? null;
        $this->description = $data['description'] ?? null;
        $this->icon = $data['icon'] ?? null;
        $this->sortOrder = $data['sort_order'] ?? 0;
        $this->isActive = $data['is_active'] ?? 1;

        // Check if slug exists
        $existing = $this->db->fetchOne(
            "SELECT id FROM categories WHERE slug = ?",
            [$this->slug]
        );
        if ($existing) {
            throw new Exception('Category with this name already exists');
        }

        // Check if parent exists
        if ($this->parentId) {
            $parent = $this->db->fetchOne(
                "SELECT id FROM categories WHERE id = ?",
                [$this->parentId]
            );
            if (!$parent) {
                throw new Exception('Parent category not found');
            }
        }

        $this->id = $this->db->insert(
            "INSERT INTO categories (name, slug, parent_id, description, icon, sort_order, is_active) 
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $this->name,
                $this->slug,
                $this->parentId,
                $this->description,
                $this->icon,
                $this->sortOrder,
                $this->isActive ? 1 : 0
            ]
        );

        return $this->id;
    }

    /**
     * Update category
     */
    public function update($data) {
        if (!$this->id) {
            throw new Exception('Category not loaded');
        }

        $allowedFields = ['name', 'parent_id', 'description', 'icon', 'sort_order', 'is_active'];
        $updates = [];
        $params = [];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $dbField = $field;
                $value = $data[$field];
                
                if ($field === 'name') {
                    $this->name = $value;
                    $this->slug = slugify($value);
                    $updates[] = "slug = ?";
                    $params[] = $this->slug;
                }
                
                if ($field === 'parent_id') {
                    if ($value == $this->id) {
                        throw new Exception('Category cannot be its own parent');
                    }
                    $this->parentId = $value;
                }
                
                $updates[] = "{$dbField} = ?";
                $params[] = $value;
            }
        }

        if (empty($updates)) {
            return false;
        }

        $params[] = $this->id;
        $query = "UPDATE categories SET " . implode(', ', $updates) . " WHERE id = ?";
        
        $result = $this->db->update($query, $params);
        
        // Reload category
        $this->load($this->id);
        
        return $result;
    }

    /**
     * Delete category
     */
    public function delete() {
        if (!$this->id) {
            throw new Exception('Category not loaded');
        }

        // Check if has subcategories
        $subCount = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM categories WHERE parent_id = ?",
            [$this->id]
        );
        if ($subCount['count'] > 0) {
            throw new Exception('Cannot delete category with subcategories');
        }

        // Check if used in listings
        $listingCount = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM listings WHERE category_id = ?",
            [$this->id]
        );
        if ($listingCount['count'] > 0) {
            throw new Exception('Cannot delete category with listings');
        }

        return $this->db->delete(
            "DELETE FROM categories WHERE id = ?",
            [$this->id]
        );
    }

    /**
     * Load subcategories
     */
    private function loadSubcategories() {
        $subcategories = $this->db->fetchAll(
            "SELECT * FROM categories WHERE parent_id = ? AND is_active = 1 ORDER BY sort_order",
            [$this->id]
        );
        
        $this->subcategories = [];
        foreach ($subcategories as $sub) {
            $cat = new Category();
            $cat->id = $sub['id'];
            $cat->name = $sub['name'];
            $cat->slug = $sub['slug'];
            $cat->parentId = $sub['parent_id'];
            $cat->description = $sub['description'];
            $cat->icon = $sub['icon'];
            $cat->sortOrder = $sub['sort_order'];
            $cat->isActive = (bool)$sub['is_active'];
            $cat->createdAt = $sub['created_at'];
            $cat->updatedAt = $sub['updated_at'];
            $this->subcategories[] = $cat;
        }
    }

    /**
     * Get listing count
     */
    public function getListingCount() {
        if (!$this->id) {
            return 0;
        }

        // Count direct listings
        $directCount = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM listings WHERE category_id = ? AND status = 'active'",
            [$this->id]
        );

        // Count listings in subcategories
        $subCount = 0;
        foreach ($this->subcategories as $sub) {
            $subCount += $sub->getListingCount();
        }

        return $directCount['count'] + $subCount;
    }

    /**
     * Get category path
     */
    public function getPath() {
        $path = [$this->name];
        if ($this->parent) {
            $path = array_merge($this->parent->getPath(), $path);
        }
        return $path;
    }

    /**
     * Get category tree
     */
    public static function getTree($parentId = null) {
        // =============================================
        // FIX: Use the existing db() function
        // =============================================
        global $db;
        $db = $db ?? db();
        
        $categories = $db->fetchAll(
            "SELECT * FROM categories WHERE parent_id " . ($parentId ? "= ?" : "IS NULL") . " AND is_active = 1 ORDER BY sort_order",
            $parentId ? [$parentId] : []
        );

        $tree = [];
        foreach ($categories as $cat) {
            $category = new Category();
            $category->load($cat['id']);
            $tree[] = $category;
        }

        return $tree;
    }

    /**
     * Get category data as array
     */
    public function toArray() {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'parent_id' => $this->parentId,
            'parent' => $this->parent ? $this->parent->toArray() : null,
            'description' => $this->description,
            'icon' => $this->icon,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'subcategories' => array_map(function($cat) { return $cat->toArray(); }, $this->subcategories),
            'listing_count' => $this->listingCount,
            'path' => $this->getPath()
        ];
    }

    // Getters
    public function getId() { return $this->id; }
    public function getName() { return $this->name; }
    public function getSlug() { return $this->slug; }
    public function getParentId() { return $this->parentId; }
    public function getDescription() { return $this->description; }
    public function getIcon() { return $this->icon; }
    public function getSortOrder() { return $this->sortOrder; }
    public function isActive() { return $this->isActive; }
    public function getCreatedAt() { return $this->createdAt; }
    public function getUpdatedAt() { return $this->updatedAt; }
    public function getParent() { return $this->parent; }
    public function getSubcategories() { return $this->subcategories; }
}