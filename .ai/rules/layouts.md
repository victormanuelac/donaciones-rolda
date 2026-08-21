---
paths:
  - 'resources/views/layouts/**'
---

# Layouts

## No envuelvas <flux:main> en otro elemento
Flux arma el layout de la app con `*:has(>[data-flux-main])` en `flux.css`, que solo aplica cuando `<flux:main>` es hijo DIRECTO del `<body>`. Si lo envuelves (por ejemplo en un `<main>` propio), el `<body>` deja de recibir `display: grid` y el sidebar se superpone al contenido en todas las vistas autenticadas.

Si necesitas un `id`, `tabindex` o atributos de accesibilidad en el landmark principal, ponlos en el propio `<flux:main>` — ya renderiza el elemento principal del layout. Ver PR #23.
