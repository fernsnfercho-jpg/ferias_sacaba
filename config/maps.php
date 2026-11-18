<?php
// Clave del navegador: restringir a Maps Embed API y al sitio permitido.
return getenv('GOOGLE_MAPS_API_KEY') ?: '';
