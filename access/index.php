<?php
// Belt-and-braces: even if .htaccess is bypassed, return 403.
http_response_code(403);
header('Content-Type: text/plain');
exit('Forbidden');
