# HiveNest Fonts Documentation

## Active Fonts (Local Hosting)

### 1. Orbitron (Heading Font)
- **Type:** Variable Font
- **File:** `Orbitron/Orbitron-VariableFont_wght.ttf` (38KB)
- **Weights:** 400-900 (fully variable)
- **Usage:** Headings, titles, cyberpunk text
- **CSS Variable:** `--font-heading`, `--font-cyber`

### 2. Rajdhani (Body Font)
- **Type:** Static Fonts (5 weights)
- **Files:**
  - `Rajdhani/Rajdhani-Light.ttf` (350KB) - Weight 300
  - `Rajdhani/Rajdhani-Regular.ttf` (344KB) - Weight 400
  - `Rajdhani/Rajdhani-Medium.ttf` (350KB) - Weight 500
  - `Rajdhani/Rajdhani-SemiBold.ttf` (355KB) - Weight 600
  - `Rajdhani/Rajdhani-Bold.ttf` (365KB) - Weight 700
- **Usage:** Body text, paragraphs, UI elements
- **CSS Variable:** `--font-primary`

### 3. FontAwesome (Icon Font)
- **Version:** 7.1.0 (Free)
- **Location:** `fontawesome-free-7.1.0-web/`
- **Usage:** Icons throughout the website
- **Include:** Via `head.php` utility

## Implementation

### Font Loading
All fonts are loaded via `/app/publichtml/assets/css/fonts.css`:
- Uses @font-face declarations
- font-display: swap for better performance
- Optimized file paths

### Integration
Fonts are automatically included via:
1. `main.css` imports `fonts.css`
2. `head.php` includes FontAwesome CSS

### Migration Complete
✅ Removed Google Fonts CDN link
✅ Removed FontAwesome CDN links
✅ All fonts now load from local files
✅ Cleaned up duplicate font files
✅ Optimized font file structure

## Performance Benefits
- Faster page loads (no external CDN requests)
- Better privacy (no third-party tracking)
- Offline functionality
- Reduced external dependencies
