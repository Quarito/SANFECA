<?php

namespace App\Exceptions\Catalogos;

use RuntimeException;

/** El catálogo no existe, está dañado o no coincide con la huella/versión esperadas. Nunca se sustituye por uno vacío. */
class CatalogoNoDisponible extends RuntimeException
{
}
