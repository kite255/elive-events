# eLive brand and design foundation
Verified sources: resources/css/app.css, resources/css/filament/admin/theme.css, app/Providers/Filament/AdminPanelProvider.php.
- Brand: eLive; product: eLive Events. Preserve casing and existing public/eLive-Logo.png. No emojis in UI.
- Primary navy #161943; secondary blue #007AB2; accent orange #FF9800.
- Public supporting defaults: background #F7F8FC, surface #FFFFFF, text #161943, muted #667085, border #E6E8EF. Admin supporting colors currently differ; standardize only after visual and accessibility review.
## Architecture proposal
One authoritative published brand configuration with safe file defaults; runtime CSS variables, semantic tokens independent of brand colors, native Filament components first and shared Blade layouts/components where necessary. Database-backed admin-only settings with validation, preview, versioning, rollback and cache invalidation are proposed, not implemented. Ensure Filament runtime integration, email/PDF rendering and asset URLs work before promising no redeployment. Platform identity remains eLive; permitted organization/event branding applies to scoped public materials. Preserve historical issued documents and templates.
## Acceptance
No uncontrolled hardcoded brand values in new UI; preserve brand and status semantics, accessible contrast, mobile and dark mode. Ordinary published color/logo changes should propagate to connected surfaces without editing individual pages; structural redesign still requires development.
