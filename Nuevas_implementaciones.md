# Registro de Nuevas Implementaciones

**Fecha:** 24-05-2024

## 1. Mejoras de Seguridad (Backend)
Se han aplicado parches críticos para fortalecer la seguridad del sistema:

### A. Protección contra CSRF (`php/gestion_equipos.php`)
- **Implementación:** Sistema de Tokens Anti-CSRF.
- **Detalle:** Se genera un `csrf_token` único en la sesión del usuario. Este token se verifica en cada solicitud POST (guardar o eliminar equipos) para asegurar que la petición proviene legítimamente del usuario y no de un enlace malicioso externo.

### B. Prevención de Fijación de Sesión (`auth.php`)
- **Implementación:** Regeneración de ID de Sesión.
- **Detalle:** Se añadió `session_regenerate_id(true)` inmediatamente después de un login exitoso. Esto invalida cualquier ID de sesión anterior, protegiendo contra ataques de secuestro de sesión.

### C. Control de Acceso / IDOR (`php/get_alarmas.php`)
- **Implementación:** Validación de Propiedad del Equipo.
- **Detalle:** Al consultar alarmas por `equipo_id` desde la web, el sistema ahora verifica que el ID solicitado pertenezca al usuario autenticado (`$_SESSION['user_id']`) antes de devolver datos sensibles.

## 2. Ajustes de Interfaz (Frontend)

### A. Carrusel de Imágenes (`index.php`)
- **Configuración:** Control de duración de diapositivas.
- **Detalle:** Se habilitó el atributo `data-bs-interval` en el componente Carousel de Bootstrap para controlar el tiempo de visualización de cada imagen (Configurable, por defecto 5000ms o 3000ms según preferencia).

---
*Documento generado automáticamente tras revisión de código.*