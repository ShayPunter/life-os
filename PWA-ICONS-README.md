# PWA Icons Setup

Your application has been configured as a Progressive Web App (PWA), but you need to create the PWA icons for a complete installation.

## Required Icons

The manifest.json file references the following icons:

- **pwa-icon-192.png** - 192x192 pixels
- **pwa-icon-512.png** - 512x512 pixels

## How to Create PWA Icons

### Option 1: Using Online Tools
1. Visit [PWA Asset Generator](https://www.pwabuilder.com/imageGenerator)
2. Upload your logo or icon (square, at least 512x512px)
3. Download the generated icons
4. Copy `pwa-icon-192.png` and `pwa-icon-512.png` to the `/public` directory

### Option 2: Using ImageMagick (if installed)
If you have ImageMagick installed, you can resize an existing icon:

```bash
# From a source icon (e.g., 1024x1024)
convert source-icon.png -resize 192x192 public/pwa-icon-192.png
convert source-icon.png -resize 512x512 public/pwa-icon-512.png
```

### Option 3: Manual Design
Create the icons manually in your preferred image editor (Figma, Photoshop, etc.) and export them as PNG files to the `/public` directory.

## Temporary Solution

For development purposes, you can copy the existing apple-touch-icon.png and resize it:

```bash
# If you have ImageMagick
convert public/apple-touch-icon.png -resize 192x192 -background white -gravity center -extent 192x192 public/pwa-icon-192.png
convert public/apple-touch-icon.png -resize 512x512 -background white -gravity center -extent 512x512 public/pwa-icon-512.png
```

## Testing Your PWA

Once the icons are in place:

1. Build your assets: `npm run build`
2. Serve your application over HTTPS (required for PWA)
3. Open your site in Chrome/Edge
4. Look for the install prompt in the address bar
5. Use Chrome DevTools > Application > Manifest to verify the setup

## Dark Mode Support

Consider creating separate icons for dark mode by updating the manifest.json with additional icon entries that include `"color_scheme": "dark"`.
