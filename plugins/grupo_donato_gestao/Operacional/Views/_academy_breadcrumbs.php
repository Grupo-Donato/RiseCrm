<?php $breadcrumbs = is_array($breadcrumbs ?? null) ? $breadcrumbs : []; ?>
<nav class="gd-academy-breadcrumbs" aria-label="Breadcrumb">
    <?php foreach ($breadcrumbs as $index => $item): ?>
        <?php if ($index > 0): ?><span class="separator">›</span><?php endif; ?>
        <?php if (!empty($item["url"]) && $index < count($breadcrumbs) - 1): ?><a href="<?php echo esc($item["url"]); ?>"><?php echo esc($item["label"]); ?></a><?php else: ?><span><?php echo esc($item["label"]); ?></span><?php endif; ?>
    <?php endforeach; ?>
</nav>
