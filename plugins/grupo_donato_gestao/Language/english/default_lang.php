<?php

/**
 * Fundação do plugin Grupo Donato — chaves gd_*.
 *
 * O locale padrão do Rise costuma ser "english". Como a UI do plugin é em
 * português (cliente brasileiro), reaproveitamos o mesmo conjunto do arquivo
 * portuguese/default_lang.php (fonte única), garantindo que as chaves gd_*
 * resolvam independentemente do locale ativo.
 */
return require __DIR__ . "/../portuguese/default_lang.php";
