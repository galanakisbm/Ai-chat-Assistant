# XML Field Mapping Guide

## Overview
The **Smart XML Field Mapping** feature allows you to use ANY XML format with the OpticWeb AI Chat module. The system **automatically detects** your XML structure and provides a user-friendly interface for mapping fields - **no XML knowledge required!**

## How It Works

### 🚀 New: 2-Step Smart Workflow

#### Step 1: Upload & Auto-Detection
1. Upload your XML file
2. System automatically:
   - Scans the XML structure
   - Detects all available tags
   - Counts products
   - Extracts sample data from first product
   - **Suggests smart field mappings**

#### Step 2: Review & Save
1. Review auto-suggested mappings
2. See live preview of first product
3. Adjust mappings if needed using dropdowns
4. Save and index products

### ✨ Smart Auto-Mapping
The system intelligently matches your XML tags to module fields using smart rules:

**Example auto-detection:**
- `<product_sku>` → Product ID ✓
- `<product_title>` → Title ✓
- `<sale_price>` → Price (with discount) ✓
- `<main_image>` → Image URL ✓
- And more...

### Default Expected Tags
The module can auto-detect these variations:

**Product ID**: `id`, `product_id`, `sku`, `product_sku`, `item_id`  
**Title**: `name`, `title`, `product_name`, `product_title`, `item_name`  
**Description**: `description`, `desc`, `full_description`, `full_desc`, `long_desc`, `details`  
**Short Description**: `short_description`, `short_desc`, `summary`, `brief`, `brief_desc`  
**Category**: `category`, `categories`, `cat`, `product_category`, `main_category`  
**Price (sale)**: `price`, `sale_price`, `current_price`, `selling_price`, `final_price`  
**Price (regular)**: `regular_price`, `original_price`, `list_price`, `msrp`, `rrp`  
**On Sale**: `onsale`, `on_sale`, `is_sale`, `sale`, `is_onsale`, `discount_active`  
**Sizes**: `size`, `sizes`, `available_sizes`, `size_options`  
**Composition**: `composition`, `material`, `materials`, `fabric`  
**Dimensions**: `dimension`, `dimensions`, `size_dimensions`, `measurements`, `product_dimensions`  
**In Stock**: `instock`, `in_stock`, `stock`, `availability`, `available`, `stock_status`  
**URL**: `url`, `link`, `product_url`, `product_link`, `permalink`  
**Image**: `image`, `img`, `picture`, `photo`, `image_url`, `main_image`

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

### ✅ Auto-Detected Mapping
The system will **automatically suggest**:
- **Product ID**: `product_sku` ✓
- **Title**: `product_title` ✓
- **Description**: `full_desc` ✓
- **Short Description**: `brief_desc` ✓
- **Main Category**: `main_category` ✓
- **Price (with discount)**: `sale_price` ✓
- **Price (without discount)**: `original_price` ✓
- **On Sale**: `is_onsale` ✓
- **Sizes**: `available_sizes` ✓
- **Composition**: `material` ✓
- **Dimensions**: `product_dimensions` ✓
- **In Stock**: `stock_status` ✓
- **Product URL**: `product_link` ✓
- **Image URL**: `main_image` ✓

**Result**: 14/14 fields auto-mapped! 🎉

## UI Preview

```
┌──────────────────────────────────────────────┐
│ Step 1: Upload Product XML Feed             │
├──────────────────────────────────────────────┤
│ [Choose File] products.xml                   │
│ [Upload & Detect Fields]                     │
└──────────────────────────────────────────────┘

✅ XML uploaded successfully!
   Products found: 150
   Fields detected: 14
   Auto-mapping applied. Please review below.

┌──────────────────────────────────────────────┐
│ Preview: First Product                       │
├──────────────────────────────────────────────┤
│ XML Tag              Value                   │
│ <product_sku>        12345                   │
│ <product_title>      Μαύρη Μπλούζα          │
│ <sale_price>         20.00                   │
│ <available_sizes>    S,M,L,XL                │
│ ...                                           │
└──────────────────────────────────────────────┘

┌──────────────────────────────────────────────┐
│ Step 2: Map XML Fields                       │
├──────────────────────────────────────────────┤
│ Product ID *         [product_sku ▼] ✓ Auto │
│ Title *              [product_title ▼] ✓     │
│ Price (discount) *   [sale_price ▼] ✓        │
│ Sizes               [available_sizes ▼]      │
│ Composition         [material ▼]             │
│ ...                                           │
│                                               │
│ [Save Mapping & Index Products]              │
└──────────────────────────────────────────────┘

✅ Settings saved!
   Products indexed: 150
```

## Rich Product Data

The enhanced search now returns and displays:
- ✓ Product sizes
- ✓ Composition/materials
- ✓ Dimensions
- ✓ Stock status
- ✓ Sale/regular pricing
- ✓ Categories

The AI assistant can now provide more detailed product information to customers!

## Usage Guide

### Step-by-Step

1. **Go to Admin Panel**
   - Navigate to **Modules → OpticWeb AI Chat → Configure**

2. **Upload XML** (Step 1)
   - Click **"Step 1: Upload Product XML Feed"**
   - Select your XML file
   - Click **"Upload & Detect Fields"**
   - System will auto-detect structure and suggest mappings

3. **Review Mappings** (Step 2)
   - Check the **Preview** panel to see sample product data
   - Review **auto-suggested mappings** in dropdowns
   - Adjust any mappings if needed
   - **Required fields** are marked with red asterisk (*)

4. **Save & Index**
   - Click **"Save Mapping & Index Products"**
   - Products will be indexed automatically
   - You'll see confirmation with product count

### Required Fields
These fields are **mandatory** for proper functionality:
- ⚠️ **Product ID** - Unique identifier
- ⚠️ **Title** - Product name
- ⚠️ **Price (with discount)** - Current selling price
- ⚠️ **Product URL** - Link to product page
- ⚠️ **Image URL** - Product image

If any required field is not mapped, you'll see a warning message.

## Benefits

✅ **Zero XML Knowledge Required**: User-friendly dropdown interface  
✅ **Automatic Detection**: System scans and detects XML structure  
✅ **Smart Suggestions**: Intelligent field matching  
✅ **Live Preview**: See sample product data before mapping  
✅ **Validation**: Warns about missing required fields  
✅ **Flexibility**: Use any XML format without modifying your feed  
✅ **Rich Data**: Support for sizes, materials, dimensions, stock  
✅ **Fast Search**: Products are cached as JSON for instant search  
✅ **Easy Setup**: 2-step workflow (upload → review)

## Troubleshooting

### "Failed to parse XML structure"
- Ensure your XML file is valid
- Check that it has a `<products>` root element with `<product>` children
- Verify the file is UTF-8 encoded

### Missing auto-mappings
- If auto-suggestions don't match all fields, manually select from dropdowns
- The system shows all available XML tags in each dropdown

### Products not showing in search
- Verify required fields are mapped (marked with *)
- Check that the XML file uploaded successfully
- Re-save mappings to re-index products
