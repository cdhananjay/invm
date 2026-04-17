# Inventory Management System

A complete, secure inventory management web application built with PHP, MySQL, HTML, CSS, and Vanilla JavaScript.

## Features

- **User Authentication**: Secure login/signup with password hashing
- **Dashboard**: Real-time metrics (Total Units, Action Required, Revenue, Net Margin) with category distribution charts
- **Inventory Management**: Full CRUD operations with search, filters, and stock tracking
- **Stock Operations**: Restock items, sell/remove with automatic revenue/profit calculation
- **Image Upload**: Secure file upload for product images
- **Account Settings**: Profile management, password change, account deletion

## Tech Stack

- **Frontend**: HTML5, Vanilla JavaScript, Custom CSS (CSS Variables)
- **Backend**: PHP (Procedural with MySQLi prepared statements)
- **Database**: MySQL
- **Environment**: XAMPP (Localhost)

## Installation

### 1. Setup XAMPP

1. Install XAMPP from https://www.apachefriends.org/
2. Start Apache and MySQL services

### 2. Database Setup

1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Create a new database named `inventory_db`
3. Import the `database.sql` file or run its contents

### 3. Application Setup

1. Copy all files to `htdocs/inventory-management/` in your XAMPP directory
2. Ensure the `uploads/` directory has write permissions

### 4. Configure Database Connection

Edit `config.php` if needed:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');  // Default XAMPP MySQL password is empty
define('DB_NAME', 'inventory_db');
```

### 5. Access the Application

Open your browser and navigate to:
- **Login**: http://localhost/inventory-management/login.php
- **Signup**: http://localhost/inventory-management/signup.php

## File Structure

```
inventory-management/
├── config.php          # Database connection & helper functions
├── index.php           # Entry point (redirects to dashboard/login)
├── login.php           # Login page
├── signup.php          # Registration page
├── logout.php          # Logout handler
├── dashboard.php       # Dashboard with metrics & charts
├── inventory.php       # Master inventory list with filters
├── add_item.php        # Add new item form
├── settings.php        # Account settings
├── style.css           # Main stylesheet
├── app.js              # Frontend JavaScript
├── database.sql        # Database schema
├── uploads/            # Uploaded product images
└── README.md           # This file
```

## Database Schema

### Users Table
- `id`, `name`, `email`, `password_hash`
- `total_revenue`, `total_profit` (auto-updated on sales)
- `created_at`

### Items Table
- `id`, `user_id` (FK), `sku_id`, `name`, `description`
- `image_path`, `location`, `category` (ENUM)
- `buy_price`, `sell_price`, `current_stock`, `threshold`
- `created_at`

## Security Features

- Password hashing with `password_hash()` (bcrypt)
- SQL injection prevention via prepared statements
- XSS prevention with `htmlspecialchars()` escaping
- Session-based authentication
- File upload validation (type, size)
- CSRF protection via session validation

## Usage Guide

### Adding Items
1. Click "Add New Item" in sidebar
2. Fill in item specifications, financials, and stock details
3. Upload product image (PNG/JPG/GIF, max 10MB)
4. Click "Save Item"

### Managing Inventory
1. View all items in the Inventory page
2. Use search bar to find items by name or SKU
3. Filter by category or stock status
4. Click an item to view details in side panel
5. Use Restock (+) or Sell/Remove (🛒) buttons for quick actions

### Selling Items
- Click the shopping cart icon to sell 1 unit
- Revenue and profit are automatically calculated and added to your totals

### Account Settings
- Update profile (name, email)
- Change password (requires current password)
- Delete account (requires password confirmation, cascades to delete all items)

## API Endpoints (AJAX)

The inventory system uses these AJAX endpoints:

| Action | Method | Parameters | Description |
|--------|--------|------------|-------------|
| `get_items` | GET | search, category, stock_status, page | Fetch paginated items |
| `get_item` | GET | id | Get single item details |
| `restock` | POST | id, quantity | Add stock |
| `sell` | POST | id | Sell 1 unit, update revenue |
| `delete` | POST | id | Delete item |

## Customization

### Theme Colors
Edit CSS variables in `style.css`:

```css
:root {
    --primary-color: #0d6efd;
    --bg-color: #f8f9fa;
    --text-primary: #1a1a2e;
    /* ... */
}
```

### Categories
Add/modify categories in `database.sql` ENUM and `inventory.php`:

```php
$categories = ['Accessories', 'Electronics', 'Home', 'Health', 'Beauty', 'YourCategory'];
```

## Troubleshooting

### Database Connection Failed
- Ensure MySQL is running in XAMPP
- Check database credentials in `config.php`

### Upload Not Working
- Check `uploads/` directory permissions
- Verify `upload_max_filesize` and `post_max_size` in php.ini

### Session Issues
- Ensure `session_start()` is called
- Check browser cookie settings

## License

This project is open source and available for educational purposes.
