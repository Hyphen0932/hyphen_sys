<?php
include_once __DIR__ . '/../build/config.php';

$sidebarMenus = [];
$sidebarPagesByMenu = [];

$menuResult = mysqli_query($conn, 'SELECT id, category, link, menu_id, menu_name, menu_icon FROM hy_user_menu');
if ($menuResult !== false) {
    while ($row = mysqli_fetch_assoc($menuResult)) {
        $sidebarMenus[] = $row;
    }

    usort($sidebarMenus, static function ($left, $right) {
        return strnatcmp((string) ($left['menu_id'] ?? ''), (string) ($right['menu_id'] ?? ''));
    });
}

$pageResult = mysqli_query($conn, 'SELECT id, menu_id, display_name, page_name, page_url, page_order FROM hy_user_pages');
if ($pageResult !== false) {
    while ($row = mysqli_fetch_assoc($pageResult)) {
        $menuId = (string) ($row['menu_id'] ?? '');
        if (!isset($sidebarPagesByMenu[$menuId])) {
            $sidebarPagesByMenu[$menuId] = [];
        }

        $sidebarPagesByMenu[$menuId][] = $row;
    }

    foreach ($sidebarPagesByMenu as &$menuPages) {
        usort($menuPages, static function ($left, $right) {
            return version_compare((string) ($left['page_order'] ?? '0'), (string) ($right['page_order'] ?? '0'));
        });
    }
    unset($menuPages);
}

function sidebar_normalize_page_url(?string $pageUrl): string
{
    $pageUrl = trim((string) $pageUrl);
    if ($pageUrl === '') {
        return '';
    }

    $pageUrl = str_replace('\\', '/', $pageUrl);
    $pageUrl = trim($pageUrl, '/');

    if (substr($pageUrl, -4) === '.php') {
        $pageUrl = substr($pageUrl, 0, -4);
    }

    if (strpos($pageUrl, 'pages/') === 0) {
        $pageUrl = substr($pageUrl, 6);
    }

    return $pageUrl;
}

function sidebar_build_menu_tree(array $pages): array
{
    $tree = [
        'direct' => [],
        'groups' => [],
    ];

    foreach ($pages as $page) {
        $pageOrder = (string) ($page['page_order'] ?? '');
        $segments = $pageOrder === '' ? [] : explode('.', $pageOrder);
        $groupKey = count($segments) >= 2 ? $segments[0] . '.' . $segments[1] : $pageOrder;
        $pageUrl = sidebar_normalize_page_url($page['page_url'] ?? '');
        $pageName = trim((string) ($page['page_name'] ?? ''));
        $isGroup = $pageUrl === '' && $pageName === '';

        if ($isGroup) {
            $tree['groups'][$groupKey] = [
                'page' => $page,
                'children' => [],
            ];
            continue;
        }

        if ($groupKey !== '' && isset($tree['groups'][$groupKey]) && $pageOrder !== ($tree['groups'][$groupKey]['page']['page_order'] ?? null)) {
            $tree['groups'][$groupKey]['children'][] = $page;
            continue;
        }

        $tree['direct'][] = $page;
    }

    return $tree;
}
?>
<aside class="app-sidebar sticky" id="sidebar">

    <div class="main-sidebar-header">
        <a href="index.html" class="header-logo">
            <img src="../../assets/images/brand-logos/desktop-logo.png" alt="logo" class="desktop-logo">
            <img src="../../assets/images/brand-logos/toggle-logo.png" alt="logo" class="toggle-logo">
            <img src="../../assets/images/brand-logos/desktop-white.png" alt="logo" class="desktop-white">
            <img src="../../assets/images/brand-logos/toggle-white.png" alt="logo" class="toggle-white">
        </a>
    </div>

    <div class="main-sidebar" id="sidebar-scroll">
        <nav class="main-menu-container nav nav-pills flex-column sub-open">
            <div class="slide-left" id="slide-left"></div>
            <ul class="main-menu">
                <?php if (!empty($sidebarMenus)): ?>
                    <?php $currentCategory = null; ?>
                    <?php foreach ($sidebarMenus as $menu): ?>
                        <?php
                        $menuCategory = trim((string) ($menu['category'] ?? 'Uncategorized'));
                        $menuCategory = $menuCategory === '' ? 'Uncategorized' : $menuCategory;
                        $menuTree = sidebar_build_menu_tree($sidebarPagesByMenu[(string) ($menu['menu_id'] ?? '')] ?? []);
                        ?>
                        <?php if ($currentCategory !== $menuCategory): ?>
                            <?php $currentCategory = $menuCategory; ?>
                            <li class="slide__category"><span class="category-name"><?php echo htmlspecialchars($menuCategory); ?></span></li>
                        <?php endif; ?>

                        <li class="slide has-sub" data-menu-id="<?php echo htmlspecialchars((string) ($menu['menu_id'] ?? '')); ?>">
                            <a href="javascript:void(0);" class="side-menu__item">
                                <i class="<?php echo htmlspecialchars((string) ($menu['menu_icon'] ?: 'ri-folder-line')); ?> side-menu__icon"></i>
                                <span class="side-menu__label"><?php echo htmlspecialchars((string) ($menu['menu_name'] ?? '')); ?></span>
                                <i class="fe fe-chevron-right side-menu__angle"></i>
                            </a>
                            <ul class="slide-menu child1">
                                <?php foreach ($menuTree['direct'] as $page): ?>
                                    <?php $normalizedUrl = sidebar_normalize_page_url($page['page_url'] ?? ''); ?>
                                    <li class="slide">
                                        <a href="../<?php echo htmlspecialchars($normalizedUrl); ?>" class="side-menu__item" data-page-url="<?php echo htmlspecialchars($normalizedUrl, ENT_QUOTES); ?>"><?php echo htmlspecialchars((string) ($page['display_name'] ?? '')); ?></a>
                                    </li>
                                <?php endforeach; ?>

                                <?php foreach ($menuTree['groups'] as $group): ?>
                                    <li class="slide has-sub">
                                        <a href="javascript:void(0);" class="side-menu__item">
                                            <?php echo htmlspecialchars((string) ($group['page']['display_name'] ?? '')); ?>
                                            <i class="fe fe-chevron-right side-menu__angle"></i>
                                        </a>
                                        <ul class="slide-menu child2">
                                            <?php foreach ($group['children'] as $page): ?>
                                                <?php $normalizedUrl = sidebar_normalize_page_url($page['page_url'] ?? ''); ?>
                                                <li class="slide">
                                                    <a href="../<?php echo htmlspecialchars($normalizedUrl); ?>" class="side-menu__item" data-page-url="<?php echo htmlspecialchars($normalizedUrl, ENT_QUOTES); ?>"><?php echo htmlspecialchars((string) ($page['display_name'] ?? '')); ?></a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="slide__category"><span class="category-name">Menu</span></li>
                    <li class="slide"><span class="side-menu__item">No menu records found</span></li>
                <?php endif; ?>
            </ul>
            <div class="slide-right" id="slide-right">
                <svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24" height="24" viewBox="0 0 24 24">
                    <path d="M10.707 17.707 16.414 12l-5.707-5.707-1.414 1.414L13.586 12l-4.293 4.293z"></path>
                </svg>
            </div>
        </nav>
    </div>

</aside>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const currentPage = normalizeSidebarPage(window.location.pathname || '');

    document.querySelectorAll('#sidebar .side-menu__item[data-page-url]').forEach(function(link) {
        const targetPage = normalizeSidebarPage(link.getAttribute('data-page-url') || '');
        if (!targetPage || targetPage !== currentPage) {
            return;
        }

        activateSidebarLink(link);
    });

    function normalizeSidebarPage(value) {
        let normalized = String(value || '').replace(/\\/g, '/').trim();
        normalized = normalized.replace(/^https?:\/\/[^/]+/i, '');
        normalized = normalized.replace(/^.*\/pages\//i, '');
        normalized = normalized.replace(/^\/+/, '');
        normalized = normalized.replace(/^\.\.\//g, '');
        normalized = normalized.replace(/\.php$/i, '');
        return normalized;
    }

    function activateSidebarLink(link) {
        link.classList.add('active');

        const slide = link.closest('.slide');
        if (slide) {
            slide.classList.add('active');
        }

        let currentMenu = link.closest('.slide-menu');
        while (currentMenu) {
            currentMenu.classList.add('active');
            currentMenu.style.display = 'block';

            const trigger = currentMenu.previousElementSibling;
            if (trigger && trigger.classList.contains('side-menu__item')) {
                trigger.classList.add('active');
            }

            const ownerSlide = currentMenu.parentElement;
            if (ownerSlide && ownerSlide.classList.contains('has-sub')) {
                ownerSlide.classList.add('open', 'active');
            }

            currentMenu = ownerSlide ? ownerSlide.closest('.slide-menu') : null;
        }
    }
});
</script>