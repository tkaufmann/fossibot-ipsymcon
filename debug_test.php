<?php
echo "Script executed at: " . date('Y-m-d H:i:s');
file_put_contents('/var/lib/symcon/debug_output.txt', 'Script ran: ' . date('Y-m-d H:i:s'));
?>