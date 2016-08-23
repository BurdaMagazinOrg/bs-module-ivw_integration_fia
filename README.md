# IVW Integration

For FIA, use a rewrite similar to this:

    RewriteCond %{HTTP_HOST} ^fbia\. [NC]
    RewriteRule ^ modules/contrib/ivw_integration/ivw_integration_fia/pages/ivw-fia-page.html [L]
