# Configuración de Limpieza Automática de Base de Datos

## 📋 Resumen
El sistema elimina automáticamente los registros de contacto que tengan más de **30 días** de antigüedad.

## ✅ **CONFIGURACIÓN ACTUAL (SIN CRON JOBS NECESARIOS)**

### **Sistema Automático Integrado**
La limpieza se ejecuta automáticamente cuando alguien visita la página web:

- ✅ **Se ejecuta máximo una vez por día**
- ✅ **Solo cuando hay registros antiguos para eliminar**
- ✅ **No afecta la velocidad de carga de la página**
- ✅ **Genera logs automáticamente**
- ✅ **Funciona cada vez que alguien entra a la web**

**¡Ya está funcionando! No necesitas hacer nada más.**

## 📊 Monitoreo y Verificación

### **Opción 1: Ejecutar manualmente (opcional)**

Visita esta URL desde tu navegador una vez al mes:
```
https://www.pflegeleicht.team/php/cleanup.php
```

### **¿Cómo funciona el sistema automático?**

1. **Cada vez que alguien visita la página web** (index.php)
2. **El sistema verifica** si ya ejecutó la limpieza hoy
3. **Si no la ha ejecutado**, busca registros mayores a 30 días
4. **Si encuentra registros antiguos**, los elimina automáticamente
5. **Registra todo en el log** para que puedas monitorearlo

**Archivos importantes:**
- `php/last_cleanup.txt` - Fecha de última limpieza
- `php/auto_cleanup_daily.php` - Script de limpieza automática
- `index.php` - Página principal (antes index.html)

## 📊 Monitoreo y Logs

### **Verificar que funciona:**
1. **Ejecutar manualmente:**
   ```
   https://www.pflegeleicht.team/php/cleanup.php
   ```

2. **Revisar el log:**
   ```
   https://www.pflegeleicht.team/php/cleanup.log
   ```

### **Ejemplo de log exitoso:**
```
2025-01-15 02:00:15 - Cleanup executed successfully
  - Records older than 30 days: 25
  - Records deleted: 25
  - Script executed from: /php/cleanup.php
  - User Agent: N/A
```

## ⚠️ Consideraciones Importantes

### **GDPR/Cumplimiento:**
- ✅ 30 días es un período razonable para seguimiento de leads
- ✅ Se registra todo en logs para auditoría
- ✅ Los datos se eliminan automáticamente

### **Backup antes de eliminar:**
Si quieres guardar datos históricos, modifica el script para:
1. Exportar a archivo antes de eliminar
2. Mover a tabla de "archivo" en lugar de eliminar

### **Cambiar el período de retención:**
Para cambiar de 30 días a otro período, editar en `cleanup.php`:
```php
$DAYS_TO_KEEP = 60; // Cambiar a 60 días, por ejemplo
```

## 🔧 Troubleshooting

**Si no se ejecuta el cron job:**
1. Verificar que Strato permita cron jobs en tu plan
2. Comprobar la ruta absoluta al archivo PHP
3. Revisar logs de Strato

**Si hay errores:**
1. Revisar `cleanup.log` para detalles
2. Verificar conexión a base de datos
3. Comprobar permisos de archivos

**Para probar manualmente:**
```bash
# En SSH (si tienes acceso):
php /path/to/cleanup.php

# O visitar desde navegador:
https://www.pflegeleicht.team/php/cleanup.php
```

## 📅 Cronograma Recomendado

- **Producción**: Cron job el día 1 de cada mes a las 2:00 AM
- **Desarrollo**: Ejecutar manualmente cuando sea necesario
- **Monitoreo**: Revisar logs mensualmente

## 🎯 Resultado Final

Con esta configuración:
✅ Los datos se eliminan automáticamente cada 30 días
✅ Cumples con regulaciones de privacidad
✅ La base de datos se mantiene liviana
✅ Tienes logs completos para auditoría
✅ Cero mantenimiento manual necesario