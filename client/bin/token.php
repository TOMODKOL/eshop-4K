<?php
// Vygeneruje náhodný klíč pro sync_token v config.php
echo bin2hex(random_bytes(32)) . "\n";
