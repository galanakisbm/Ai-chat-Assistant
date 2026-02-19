# XML Field Mapping Guide

## Overview
The XML Field Mapping feature allows you to use ANY XML format with the OpticWeb AI Chat module. You can map your custom XML tags to the module's expected fields through the admin panel.

## How It Works

### Default Mapping
By default, the module expects these XML tags:
- `id` - Product ID
- `name` - Product title
- `description` - Full description
- `short_description` - Short description
- `category` - Category name
- `price` - Sale price
- `regular_price` - Regular price
- `onsale` - On sale status (1/0 or Y/N)
- `size` - Available sizes (comma-separated)
- `composition` - Material/composition
- `dimension` - Dimensions
- `instock` - Stock status (Y/N or 1/0)
- `url` - Product URL
- `image` - Image URL

### Custom Mapping
If your XML uses different tag names, configure the mapping in the admin panel:

**Admin Panel → Modules → OpticWeb AI Chat → XML Field Mapping**

## Example: Custom XML Format

### Your Custom XML
```xml
<?xml version="1.0" encoding="UTF-8"?>
<products>
  <product>
    <product_sku>12345</product_sku>
    <product_title>Μαύρη Μπλούζα Premium</product_title>
    <full_desc>Εξαιρετικής ποιότητας μπλούζα...</full_desc>
    <brief_desc>Κλασική μπλούζα βαμβακερή</brief_desc>
    <main_category>Ρούχα</main_category>
    <sale_price>20.00</sale_price>
    <original_price>25.00</original_price>
    <is_onsale>1</is_onsale>
    <available_sizes>S,M,L,XL,XXL</available_sizes>
    <material>100% Cotton</material>
    <product_dimensions>Standard Fit</product_dimensions>
    <stock_status>Y</stock_status>
    <product_link>https://example.com/product/12345</product_link>
    <main_image>https://example.com/images/12345.jpg</main_image>
  </product>
</products>
```

### Field Mapping Configuration
In the admin panel, set:
- **Product ID**: `product_sku`
- **Title**: `product_title`
- **Description**: `full_desc`
- **Short Description**: `brief_desc`
- **Main Category**: `main_category`
- **Price (with discount)**: `sale_price`
- **Price (without discount)**: `original_price`
- **On Sale**: `is_onsale`
- **Sizes**: `available_sizes`
- **Composition**: `material`
- **Dimensions**: `product_dimensions`
- **In Stock**: `stock_status`
- **Product URL**: `product_link`
- **Image URL**: `main_image`

## Rich Product Data

The enhanced search now returns and displays:
- ✓ Product sizes
- ✓ Composition/materials
- ✓ Dimensions
- ✓ Stock status
- ✓ Sale/regular pricing
- ✓ Categories

The AI assistant can now provide more detailed product information to customers!

## Usage

1. Go to **Admin Panel → Modules → OpticWeb AI Chat**
2. Configure your XML field mappings in the "XML Field Mapping" section
3. Upload your XML file in the "Product Feed (XML)" field
4. Click "Save"
5. The system will automatically index your products using your custom field names

## Benefits

✓ **Flexibility**: Use any XML format without modifying your feed  
✓ **Rich Data**: Support for sizes, materials, dimensions, stock  
✓ **Fast Search**: Products are cached as JSON for instant search  
✓ **Easy Setup**: Simple admin interface for mapping configuration
