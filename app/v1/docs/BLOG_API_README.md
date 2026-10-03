# Blog API Documentation

Base URL: `https://dazuaihub.com/`

All blog endpoints live under `public/api/blog/` and are reached at their **literal file path** (no pretty-URL rewriting for `/api/*`). Example: `https://dazuaihub.com/api/blog/list.php`.

## General notes

- Every endpoint sends `Content-Type: application/json`.
- **All responses return HTTP 200**, even on validation errors, auth failures, or exceptions. There is no use of `http_response_code()` on these endpoints — **always check the `success` boolean in the JSON body**, never the HTTP status code.
- Admin/write endpoints require an active PHP session with `$_SESSION['user_id']` set and `$_SESSION['role_id']` ≤ 2 (admin/staff). If not authenticated:
  ```json
  { "success": false, "message": "Unauthorized access" }
  ```
- There is no CSRF token requirement on these specific endpoints.

---

## Public / Read Endpoints (no auth required)

### `GET /api/blog/list.php`

List blog posts.

**Query params**

| Param | Type | Default | Notes |
|---|---|---|---|
| `status` | string | `published` | e.g. `draft`, `published`, `archived` |
| `limit` | int | `12` | |
| `offset` | int | `0` | |

**Success response**
```json
{
  "success": true,
  "data": [
    {
      "id": 12,
      "title": "5 Tips for Learning AI",
      "slug": "5-tips-for-learning-ai",
      "excerpt": "Short summary of the post...",
      "featured_image": "uploads/blogs/abc123.jpg",
      "category_id": 3,
      "category_name": "AI & Machine Learning",
      "category_color": "#667eea",
      "author_id": 1,
      "author_name": "Jane Doe",
      "status": "published",
      "view_count": 245,
      "is_featured": 1,
      "published_at": "2026-06-01 10:00:00",
      "created_at": "2026-05-30 09:12:00",
      "updated_at": "2026-06-01 10:00:00"
    }
  ]
}
```

**Error response**
```json
{ "success": false, "message": "<exception message>" }
```

---

### `GET /api/blog/get.php`
Query params**

| Param | Type | Required | Notes |
|---|---|---|---|
| `slug` | string | one of `slug`/`id` | |
| `id` | int | one of `slug`/`id` | |
| `increment_view` | `1` | optional | increments the post's view count |

**Su
Get a single blog post with its sections, FAQs, tags, and related posts.

**ccess response**
```json
{
  "success": true,
  "data": {
    "id": 12,
    "title": "5 Tips for Learning AI",
    "slug": "5-tips-for-learning-ai",
    "content": "<full HTML/long content>",
    "excerpt": "Short summary...",
    "featured_image": "uploads/blogs/abc123.jpg",
    "category_id": 3,
    "category_name": "AI & Machine Learning",
    "author_id": 1,
    "author_name": "Jane Doe",
    "status": "published",
    "view_count": 246,
    "is_featured": 1,
    "meta_title": "5 Tips for Learning AI",
    "meta_description": "...",
    "meta_keywords": "ai, learning, tips",
    "published_at": "2026-06-01 10:00:00",
    "created_at": "2026-05-30 09:12:00",
    "updated_at": "2026-06-01 10:00:00",
    "sections": [
      {
        "id": 41,
        "blog_id": 12,
        "section_type": "paragraph",
        "title": null,
        "content": "Paragraph text...",
        "media_url": null,
        "gallery_images": null,
        "media_type": null,
        "video_id": null,
        "link_label": null,
        "cta_type": null,
        "cta_value": null,
        "cta_label": null,
        "faq_items": null,
        "sort_order": 1,
        "created_at": "2026-05-30 09:15:00"
      }
    ],
    "faqs": [
      { "id": 5, "blog_id": 12, "question": "Is this free?", "answer": "Yes.", "sort_order": 1, "is_active": 1 }
    ],
    "tags": [
      { "id": 2, "name": "AI", "slug": "ai" }
    ],
    "related_posts": [
      { "id": 15, "title": "Another Post", "slug": "another-post", "featured_image": "uploads/blogs/x.jpg" }
    ]
  }
}
```

**Error responses**
```json
{ "success": false, "message": "Blog identifier required" }
```
```json
{ "success": false, "message": "Blog not found" }
```
```json
{ "success": false, "message": "<exception message>" }
```

---

### `GET /api/blog/search.php`

**Query params**

| Param | Type | Required |
|---|---|---|
| `q` or `keyword` | string | yes |

**Success response**
```json
{
  "success": true,
  "data": [ /* array of blog rows, same shape as list.php */ ],
  "keyword": "ai"
}
```

**Error responses**
```json
{ "success": false, "message": "Search keyword required" }
```
```json
{ "success": false, "message": "<exception message>" }
```

---

### `GET /api/blog/categories.php`

No params.

**Success response**
```json
{
  "success": true,
  "data": [
    {
      "id": 3,
      "name": "AI & Machine Learning",
      "slug": "ai-machine-learning",
      "description": "Posts about AI",
      "color": "#667eea",
      "created_by": 1,
      "created_at": "2026-01-10 12:00:00"
    }
  ]
}
```

**Error response**
```json
{ "success": false, "message": "<exception message>" }
```

> **Note:** `public/api/blog/tags.php` exists on disk but is currently an empty file — it is not a functioning endpoint.

---

## Admin / Write Endpoints (session + `role_id` ≤ 2 required)

Shared response helper: `sendJsonResponse($success, $message, $data = [])` → merges `{success, message}` with any extra `$data`.

### `POST /api/blog/create.php`

**Body** (`multipart/form-data`)

| Field | Type | Required |
|---|---|---|
| `title` | string | yes |
| `content` | string | yes |
| `excerpt` | string | no |
| `category_id` | int | no |
| `status` | `draft`\|`published`\|`archived` | no (default `draft`) |
| `is_featured` | truthy value | no |
| `meta_title` | string | no |
| `meta_description` | string | no |
| `meta_keywords` | string | no |
| `tags` | comma-separated string | no |
| `featured_image` | file | no |

**Success**
```json
{ "success": true, "message": "Blog post created successfully", "blog_id": 123 }
```

**Errors**
```json
{ "success": false, "message": "Unauthorized access" }
{ "success": false, "message": "Invalid request method" }
{ "success": false, "message": "Title and content are required", "blog_id": null }
{ "success": false, "message": "Server error: <details>" }
```

---

### `POST /api/blog/update.php`

**Body** (`multipart/form-data`, partial update — only submitted fields are changed)

| Field | Type | Required |
|---|---|---|
| `blog_id` | int | yes |
| ...any field from `create.php` | | no |
| `featured_image` | file | no |

**Success**
```json
{ "success": true, "message": "Blog post updated successfully" }
```

**Errors**
```json
{ "success": false, "message": "Blog ID is required" }
{ "success": false, "message": "Unauthorized access" }
{ "success": false, "message": "Invalid request method" }
{ "success": false, "message": "Failed to update blog post" }
{ "success": false, "message": "Server error: <details>" }
```

---

### `POST /api/blog/delete.php`

**Body** (raw JSON via `php://input`)
```json
{ "id": 123 }
```
Deletes the blog post and its `featured_image` file from disk if present.

**Success**
```json
{ "success": true, "message": "Blog post deleted successfully" }
```

**Errors**
```json
{ "success": false, "message": "Blog ID is required" }
{ "success": false, "message": "Unauthorized access" }
{ "success": false, "message": "Failed to delete blog post" }
{ "success": false, "message": "Server error: <details>" }
```

---

### `POST /api/blog/create_category.php`

**Body** (raw JSON)

| Field | Type | Required |
|---|---|---|
| `name` | string | yes |
| `description` | string | no |
| `color` | string (hex) | no, default `#667eea` |

**Success**
```json
{ "success": true, "message": "Category created successfully", "category_id": 45 }
```

**Errors**
```json
{ "success": false, "message": "Category name is required" }
{ "success": false, "message": "Category already exists" }
{ "success": false, "message": "Unauthorized access" }
{ "success": false, "message": "Server error: <details>" }
```

---

### `POST /api/blog/add_faq.php`

**Body** (`form-data`/`x-www-form-urlencoded`)

| Field | Type | Required |
|---|---|---|
| `blog_id` | int | yes |
| `question` | string | yes |
| `answer` | string | yes |
| `sort_order` | int | no |

**Success**
```json
{ "success": true, "message": "FAQ added successfully" }
```

**Errors**
```json
{ "success": false, "message": "Failed to add FAQ" }
{ "success": false, "message": "Unauthorized access" }
{ "success": false, "message": "Server error: <details>" }
```

---

### `POST /api/blog/add_section.php`

Adds a content block to a post.

**Body** (`multipart/form-data`)

| Field | Type | Required | Notes |
|---|---|---|---|
| `blog_id` | int | yes | |
| `section_type` | `heading`\|`paragraph`\|`image`\|`image_gallery`\|`youtube`\|`link`\|`quote`\|`cta`\|`faq` | yes | |
| `title` | string | conditional | used by `heading`, etc. |
| `content` | string | conditional | text body for `paragraph`/`quote`/etc. |
| `media_url` | string | conditional | for `youtube`/`link` |
| `link_label` | string | conditional | |
| `cta_type`, `cta_value`, `cta_label` | string | conditional | for `cta` blocks |
| `faq_question[]`, `faq_answer[]` | array | conditional | for `faq` blocks |
| `sort_order` | int | no | |
| `media_file` | file | conditional | single image for `image` type |
| `gallery_files[]` | files | conditional | multiple images for `image_gallery` type |

**Success**
```json
{ "success": true, "message": "Block added successfully" }
```

**Errors**
```json
{ "success": false, "message": "Blog ID is required" }
{ "success": false, "message": "Failed to add block" }
{ "success": false, "message": "Unauthorized access" }
{ "success": false, "message": "Server error: <details>" }
```

---

### `POST /api/blog/update_section.php`

Same field set as `add_section.php`, plus:

| Field | Type | Required | Notes |
|---|---|---|---|
| `section_id` | int | yes | |
| `existing_gallery_images[]` | array | no | retained gallery image paths |
| `gallery_images_touched` | flag | no | signals gallery diffing should run |

**Success**
```json
{ "success": true, "message": "Block updated successfully" }
```

**Errors**
```json
{ "success": false, "message": "Section ID is required" }
{ "success": false, "message": "Failed to update block" }
{ "success": false, "message": "Unauthorized access" }
{ "success": false, "message": "Server error: <details>" }
```

---

### `POST /api/blog/delete_section.php`

**Body**

| Field | Type | Required |
|---|---|---|
| `section_id` | int | yes |

**Success**
```json
{ "success": true, "message": "Block deleted successfully" }
```

**Errors**
```json
{ "success": false, "message": "Section ID is required" }
{ "success": false, "message": "Failed to delete block" }
{ "success": false, "message": "Unauthorized access" }
{ "success": false, "message": "Server error: <details>" }
```

---

### `POST /api/blog/reorder_sections.php`

**Body**

| Field | Type | Required | Notes |
|---|---|---|---|
| `order[]` | array of section IDs | yes | array index becomes the new `sort_order` |

**Success**
```json
{ "success": true, "message": "Order saved" }
```

**Errors**
```json
{ "success": false, "message": "Order is required" }
{ "success": false, "message": "Failed to save order" }
{ "success": false, "message": "Unauthorized access" }
{ "success": false, "message": "Server error: <details>" }
```

---

## Non-API page routes (HTML, not JSON)

These render server-side HTML and do **not** return JSON, so they're excluded from the API above but listed for completeness:

- `GET /blogs/index.php` — blog listing page (`?search=`, `?category=`)
- `GET /blogs/readmore.php?slug=<slug>` — single post page
- `GET /blogs/search.php?q=<keyword>` — legacy search results page

## Data model reference

**`blogs`**: `id, title, slug, excerpt, content, featured_image, category_id, author_id, status, view_count, is_featured, meta_title, meta_description, meta_keywords, published_at, created_at, updated_at`

**`blog_categories`**: `id, name, slug, description, color, created_by, created_at`

**`blog_sections`**: `id, blog_id, section_type, title, content, media_url, gallery_images (JSON), media_type, video_id, link_label, cta_type, cta_value, cta_label, faq_items (JSON), sort_order, created_at`

**`blog_faqs`**: `id, blog_id, question, answer, sort_order, is_active, created_at`

**`blog_tags`** / **`blog_tag_relations`**: `id, name, slug` / `blog_id, tag_id`

## Known gaps

- `tags.php` is an empty file (no functioning endpoint).
- `BlogController` has `updateFaq`, `deleteFaq`, `updateCategory`, `deleteCategory` methods, but no corresponding HTTP endpoint files currently expose them.
