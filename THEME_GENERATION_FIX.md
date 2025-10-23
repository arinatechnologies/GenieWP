# GenieWP - Theme Generation Fix

## Summary of Changes

### Problem
The plugin was throwing an error: **"An unexpected error occurred. Please try again."** when attempting to generate a theme.

### Root Cause
1. The `Theme_Generator` class was not being loaded in `geniewp.php`
2. The `ajax_generate_theme()` method was registered but not implemented in the `Main` class
3. The AJAX response was missing required URLs (`activate_url` and `customize_url`)

### Files Modified

#### 1. `geniewp.php`
**Change:** Added `class-theme-generator.php` to the list of core files to load.

```php
$core_files = [
    'inc/class-main.php',
    'inc/class-api.php',
    'inc/class-theme-generator.php',  // ← ADDED
    'inc/helpers.php'
];
```

#### 2. `inc/class-main.php`
**Change:** Added the `ajax_generate_theme()` method to handle AJAX requests.

**Key Features:**
- Verifies WordPress nonce for security
- Checks user capabilities (`manage_options`)
- Sanitizes all form inputs
- Validates required fields (site_name, business_type)
- Calls `Theme_Generator` to create the theme
- Returns success/error responses in proper JSON format
- Includes `activate_url` and `customize_url` in the response

```php
public function ajax_generate_theme() {
    // Verify nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'geniewp_generate_theme' ) ) {
        wp_send_json_error( array(
            'message' => __( 'Security check failed. Please refresh the page and try again.', 'geniewp' ),
        ) );
    }

    // Check user permissions
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array(
            'message' => __( 'You do not have permission to generate themes.', 'geniewp' ),
        ) );
    }

    // Get and sanitize form data
    $form_data = array(
        'site_name'       => sanitize_text_field( $_POST['site_name'] ?? '' ),
        'business_type'   => sanitize_text_field( $_POST['business_type'] ?? '' ),
        'tagline'         => sanitize_text_field( $_POST['tagline'] ?? '' ),
        'description'     => sanitize_textarea_field( $_POST['description'] ?? '' ),
        'primary_color'   => sanitize_hex_color( $_POST['primary_color'] ?? '#2563eb' ),
        'secondary_color' => sanitize_hex_color( $_POST['secondary_color'] ?? '#10b981' ),
    );

    // Validate required fields
    if ( empty( $form_data['site_name'] ) || empty( $form_data['business_type'] ) ) {
        wp_send_json_error( array(
            'message' => __( 'Please fill in all required fields (Site Name and Business Type).', 'geniewp' ),
        ) );
    }

    // Generate theme
    $generator = new Theme_Generator();
    $result = $generator->generate_theme( $form_data );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array(
            'message' => $result->get_error_message(),
        ) );
    }

    // Send success response
    wp_send_json_success( array(
        'message'       => sprintf( __( 'Theme "%s" created successfully!', 'geniewp' ), $result['theme_name'] ),
        'theme_slug'    => $result['theme_slug'],
        'theme_name'    => $result['theme_name'],
        'activate_url'  => admin_url( 'themes.php' ),
        'customize_url' => admin_url( 'customize.php?theme=' . $result['theme_slug'] ),
    ) );
}
```

---

## How It Works Now

### 1. **Form Submission Flow**

```
User fills form → Clicks "Generate Theme" 
  ↓
JavaScript validates & sends AJAX request
  ↓
WordPress routes to ajax_generate_theme()
  ↓
Security checks (nonce + capabilities)
  ↓
Data sanitization & validation
  ↓
Theme_Generator::generate_theme()
  ↓
Success response with theme details
  ↓
UI updates with success message + activation links
```

### 2. **Theme Generation Process**

The `Theme_Generator` class:
1. Checks if OpenAI API key is available
2. If yes: Attempts AI-powered theme generation
3. If AI fails or no key: Falls back to basic template
4. Creates theme directory: `/wp-content/themes/geniewp-{slug}/`
5. Generates essential files:
   - `style.css` (theme metadata)
   - `theme.json` (block theme configuration with colors)
   - `templates/index.html` (main template)
   - `templates/front-page.html` (homepage template)
   - `parts/header.html` (header partial)
   - `parts/footer.html` (footer partial)
   - `README.md` (documentation)

### 3. **AI Integration**

If OpenAI API key is configured:
- Sends a structured prompt with site details
- Requests theme.json with custom colors
- Requests modern, professional design suggestions
- Model used: `gpt-3.5-turbo`
- Falls back gracefully if API fails

---

## Testing Instructions

### Prerequisites
- WordPress 6.5 or higher
- PHP 8.1 or higher
- GenieWP plugin installed and activated
- Admin access to WordPress dashboard

### Step 1: Access the Plugin
1. Log in to WordPress admin
2. Navigate to **Appearance → GenieWP** (or check Settings menu)
3. You should see the theme generation form

### Step 2: Optional - Configure OpenAI API
1. Go to **Settings → GenieWP Settings**
2. Enter your OpenAI API key
3. Save changes
4. **Note:** If no API key is provided, the plugin will generate a basic theme template

### Step 3: Generate a Theme

**Fill out the form:**
- **Website Name / Brand** (required): e.g., "My Photography Studio"
- **Business Type / Industry** (required): e.g., "Photography"
- **Tagline** (optional): e.g., "Capturing Life's Beautiful Moments"
- **Description** (optional): Brief description of your site
- **Primary Color** (optional): Choose your brand color (default: #2563eb - blue)
- **Secondary Color** (optional): Choose accent color (default: #10b981 - green)

**Click "Generate Theme"**

### Step 4: Expected Results

**Success Scenario:**
- Loading spinner appears
- After processing (5-30 seconds depending on AI):
- Success message displays:
  ```
  ✅ Theme Generated Successfully!
  Theme Name: My Photography Studio
  Theme Slug: geniewp-my-photography-studio
  
  [Activate Theme] [Customize]
  ```

**Error Scenarios:**

1. **Missing Required Fields:**
   ```
   ❌ Error: Please fill in all required fields (Site Name and Business Type).
   ```

2. **Permission Error:**
   ```
   ❌ Error: You do not have permission to generate themes.
   ```

3. **Security Error:**
   ```
   ❌ Error: Security check failed. Please refresh the page and try again.
   ```

4. **File System Error:**
   ```
   ❌ Error: Failed to create theme directory. Check file permissions.
   ```

### Step 5: Activate the Theme
1. Click the **"Activate Theme"** button in the success message
   - OR go to **Appearance → Themes**
2. Find your newly generated theme
3. Click **"Activate"**
4. Visit your site to see the new theme in action

### Step 6: Customize (Optional)
1. Click the **"Customize"** button
   - OR go to **Appearance → Customize**
2. Edit colors, fonts, layouts using the Site Editor

---

## Verification Checklist

- [ ] Plugin loads without PHP errors
- [ ] Form displays correctly with all fields
- [ ] Required field validation works
- [ ] Color pickers function properly
- [ ] AJAX request sends successfully
- [ ] Nonce verification passes
- [ ] Theme is created in `/wp-content/themes/`
- [ ] All theme files are generated correctly
- [ ] Success message displays with proper links
- [ ] "Activate Theme" link works
- [ ] "Customize" link works
- [ ] Theme appears in Appearance → Themes
- [ ] Theme can be activated successfully
- [ ] No JavaScript console errors

---

## Troubleshooting

### Issue: "Security check failed"
**Solution:** Refresh the page and try again. This happens if the page has been open for too long.

### Issue: "Failed to create theme directory"
**Solution:** 
1. Check file permissions on `/wp-content/themes/`
2. Ensure WordPress has write access
3. Check PHP error log for details

### Issue: Form doesn't appear
**Solution:**
1. Ensure plugin is activated
2. Check that you're logged in as admin
3. Clear browser cache
4. Check for JavaScript errors in browser console

### Issue: AJAX request fails silently
**Solution:**
1. Open browser Developer Tools (F12)
2. Go to Network tab
3. Submit the form
4. Look for `admin-ajax.php` request
5. Check the response for error details

### Issue: OpenAI API errors
**Solution:**
1. Verify API key is correct
2. Check OpenAI account has credits
3. Plugin will fall back to basic theme if API fails

---

## Theme File Structure

After generation, your theme will have this structure:

```
/wp-content/themes/geniewp-{slug}/
├── style.css                    # Theme metadata & styles
├── theme.json                   # Block theme configuration
├── README.md                    # Theme documentation
├── templates/
│   ├── index.html              # Default template
│   └── front-page.html         # Homepage template
└── parts/
    ├── header.html             # Site header
    └── footer.html             # Site footer
```

---

## Next Steps

### For Users:
1. Generate your first theme
2. Activate and customize it
3. Add more templates as needed using the Site Editor

### For Developers:
1. The `Theme_Generator` class can be extended
2. Add more template variations
3. Enhance AI prompts for better results
4. Add support for more theme files (404.html, archive.html, etc.)
5. Implement theme export/import functionality

---

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Review WordPress error logs: `/wp-content/debug.log`
3. Enable WordPress debug mode in `wp-config.php`:
   ```php
   define( 'WP_DEBUG', true );
   define( 'WP_DEBUG_LOG', true );
   ```

---

## Technical Details

### Security Measures
- WordPress nonce verification
- Capability checks (`manage_options`)
- Input sanitization (text, textarea, hex colors)
- No direct file includes
- Prepared theme directory creation

### Performance
- Theme generation: 5-30 seconds
- Basic fallback: ~2-5 seconds
- No impact on site front-end performance

### Compatibility
- WordPress 6.5+
- PHP 8.1+
- Block themes only
- Modern browsers (ES6 JavaScript)

---

**Last Updated:** October 23, 2025
**Version:** 1.0.0
**Status:** ✅ Fixed and Ready for Testing
