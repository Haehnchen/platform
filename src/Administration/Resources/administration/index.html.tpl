<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shopware Administration WebPack Hot Reloading Server</title>
</head>
<body>
    <div id="app"></div>
    <script type="text/javascript">
        Shopware.Application.start({ features: <%= featureFlags %> });
    </script>
</body>
</html>
