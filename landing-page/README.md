# TACI Petroleum - Landing Page

A modern, fully responsive, and animated landing page for TACI Petroleum Company Limited - Nigeria's premier petroleum marketing and freighting company.

## 🎯 Features

### ✨ Design & UX
- **Modern Premium Design** — Fortune 500 aesthetic with deep navy, gold accents, and fire orange highlights
- **Fully Responsive** — 320px (mobile) to 2560px (4K) with fluid typography using `clamp()`
- **Smooth Animations** — Scroll-triggered fade-in effects, particle background, animated counters
- **Dark Mode Ready** — CSS variables for easy theming and dark mode support
- **Accessibility First** — ARIA labels, keyboard navigation, skip links, reduced-motion support

### 🎨 Sections
1. **Navigation** — Sticky navbar with smooth scroll, mobile hamburger menu
2. **Hero** — Full-viewport cinematic section with animated truck and particle effects
3. **About** — Company history with timeline, trust badges, and responsive media
4. **Stats** — Animated counter cards (30+ Trucks, 45,000L Capacity, 24+ Years, 1000+ Deliveries)
5. **Services** — 6-service card grid with 3D hover effects and icons
6. **Operations** — Feature highlights with alternating layouts
7. **Fleet Showcase** — Auto-scrolling carousel of truck types
8. **Testimonials** — Customer testimonials with rating stars
9. **Contact** — Multi-field contact form with validation + embedded Google Maps
10. **Newsletter** — Email subscription form
11. **Footer** — 4-column layout with links, social media, and company info

### 🔧 Technical Stack
- **Pure HTML5** — Semantic, accessible markup
- **CSS3** — Variables, gradients, transforms, animations, media queries
- **Vanilla JavaScript** — No jQuery, no frameworks (Intersection Observer, Canvas for particles)
- **Performance** — Lazy loading, optimized fonts, minimal bundle size

## 📁 Project Structure

```
landing-page/
├── index.html              # Main HTML file (semantic, accessibility-focused)
├── css/
│   └── styles.css          # All CSS with CSS variables, animations, responsive design
├── js/
│   ├── animations.js       # Scroll animations, particle background, smooth scroll
│   ├── form.js             # Form validation, carousel, newsletter
│   └── main.js             # Utilities, lazy loading, accessibility helpers
├── assets/
│   ├── images/             # Optimized images (for future use)
│   ├── icons/              # SVG icons (embedded in HTML)
│   └── fonts/              # Google Fonts (loaded via CDN)
└── README.md               # This file
```

## 🚀 Getting Started

### Installation
No build process required! Simply serve the files:

```bash
# Using Python 3
python3 -m http.server 8000

# Using Node.js http-server
npx http-server

# Using PHP
php -S localhost:8000

# Or open index.html directly in a browser
```

Visit `http://localhost:8000` in your browser.

### Customization

#### Colors
Edit CSS variables in `css/styles.css`:
```css
:root {
    --primary: #0A1628;           /* Deep navy */
    --accent-gold: #F5A623;       /* Gold */
    --accent-orange: #E8560A;     /* Fire orange */
    --secondary-white: #FFFFFF;   /* White */
}
```

#### Content
- **Company Info** — Update text in HTML sections
- **Contact Details** — Edit phone, email, address in footer and contact form
- **Google Maps** — Replace embedded map iframe with your location
- **Partner Logos** — Add actual logos in `.partners-grid`

#### Animations
- **Scroll Speed** — Adjust `observerOptions.rootMargin` in `animations.js`
- **Particle Count** — Modify `particleCount` calculation in `ParticleBackground`
- **Counter Speed** — Change `duration` in `animateCounters()` method

## 🎯 Performance

### Lighthouse Scores (Target)
- ✓ Performance: 95+
- ✓ Accessibility: 95+
- ✓ Best Practices: 95+
- ✓ SEO: 100

### Optimizations Implemented
- ✓ Semantic HTML5 for better SEO and accessibility
- ✓ CSS variables for reduced file size
- ✓ Intersection Observer for efficient scroll animations
- ✓ Canvas-based particle background (lightweight)
- ✓ Lazy loading images with `loading="lazy"`
- ✓ Font display strategy: `display=swap` for faster rendering
- ✓ Minimal JavaScript (no frameworks)
- ✓ Prefers-reduced-motion support for accessibility

### Load Time
- First Contentful Paint (FCP): < 1.5s
- Largest Contentful Paint (LCP): < 2.5s
- Cumulative Layout Shift (CLS): < 0.1

## 📱 Responsive Breakpoints

- **Mobile (< 480px)** — Single column, optimized touch targets
- **Tablet (480px - 768px)** — 2-column grids, adjusted spacing
- **Desktop (> 768px)** — Full multi-column layouts
- **4K (> 1920px)** — Fluid scaling with `clamp()`

## 🔐 Security

- ✓ Content Security Policy (CSP) ready
- ✓ No inline scripts (except necessary initialization)
- ✓ Form validation (client-side + backend recommended)
- ✓ No sensitive data in HTML or JavaScript

## 🌐 Browser Support

- ✓ Chrome/Edge 90+
- ✓ Firefox 88+
- ✓ Safari 14+
- ✓ Mobile browsers (iOS Safari 14+, Chrome Mobile)

## 🔗 Links

### Navigation
- **Home** → `#home` (hero section)
- **About** → `#about` (about section)
- **Services** → `#services` (services grid)
- **Operations** → `#operations` (features)
- **Contact** → `#contact` (contact form)
- **Staff Portal** → `/web-app/login` (web app)

### External
- **Google Fonts** — Playfair Display, Poppins, Roboto Mono
- **Google Maps** — Embedded in contact section
- **Social Media** — Facebook, Twitter, LinkedIn

## 📝 Form Validation

### Contact Form
- **Name** — Min 3 characters
- **Email** — Valid email format
- **Phone** — Optional, valid format if provided
- **Subject** — Required dropdown
- **Message** — Min 10 characters

### Newsletter Form
- **Email** — Valid email format required

Validation is client-side (JavaScript). Backend validation recommended for production.

## 🛠️ Development Tips

### Adding New Sections
1. Add HTML in `index.html` with semantic tags
2. Add CSS in `styles.css` following the organization
3. Add JavaScript in `js/` files if needed
4. Use `.fade-in-*` classes for automatic scroll animations

### Debugging Animations
```javascript
// In browser console
new ScrollAnimations(); // Reinitialize
new ParticleBackground('particle-canvas'); // Restart particles
```

### Mobile Testing
```bash
# Chrome DevTools: Press F12, click device toolbar (Ctrl+Shift+M)
# Or use actual devices with local IP:
# http://192.168.x.x:8000
```

## 📊 SEO

- ✓ Semantic HTML5 (`<header>`, `<nav>`, `<section>`, `<article>`, `<footer>`)
- ✓ Meta description and keywords
- ✓ Open Graph tags for social sharing
- ✓ Twitter Card meta tags
- ✓ Structured data ready (add JSON-LD if needed)
- ✓ Mobile-friendly (responsive design)
- ✓ Fast load times

## ♿ Accessibility (WCAG 2.1 AA)

- ✓ ARIA labels for icon buttons
- ✓ Semantic HTML structure
- ✓ Color contrast: WCAG AA minimum
- ✓ Keyboard navigation support
- ✓ Skip to main content link
- ✓ Reduced motion support
- ✓ Form labels and validation messages
- ✓ Alt text for images (ready)

## 🤝 Integration with Web App

The landing page links to the web app at `/web-app/login`:
```html
<a href="/web-app/login">Staff Portal</a>
```

Update the path according to your deployment structure.

## 📄 License

© 2026 TACI Petroleum Company Limited. All Rights Reserved.

## 🚀 Deployment

### Static Hosting (Vercel, Netlify, GitHub Pages)
```bash
# No build process needed
# Simply push the landing-page folder
```

### Self-Hosted
```bash
# Copy landing-page folder to web server
# Point domain to index.html
# Ensure HTTPS is enabled
```

### With Backend
Update form endpoints in `js/form.js`:
```javascript
// Replace localhost with your API endpoint
fetch('/api/contact', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(formData)
})
```

## 📧 Contact & Support

- **Company Website** — https://www.tacipetroleum.com
- **Email** — info@tacipetroleum.com
- **Phone** — +234 803-788-0018
- **Address** — Kaduna-Kachia Road, Kujama, Kaduna State, Nigeria

---

**Built for Excellence.** ⚡
