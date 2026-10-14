<?php

/**
 * Checks the site's navbar and footer.
 *
 * These two pieces used to exist TWICE: once in Vue (app/components/AppHeader.vue,
 * AppFooter.vue) for the home/about/contact pages, and again as a hand-copy in
 * PHP (public/includes/nav.php, footer.php) for the news pages. Keeping the two
 * in step by hand was the most delicate part of the project.
 *
 * That is over. The news pages are Nuxt pages now, so the navbar and footer
 * live in exactly ONE place: the Vue components. This check reads those and
 * confirms the pieces that matter are still there.
 *
 * It is a plain text check, not a render: mounting a Vue component would need
 * the whole build. It reads the component source and the two things a copy is
 * most likely to lose — links, and the numbers and addresses people phone and
 * visit.
 *
 *   php tests/chrome-check.php
 */

$root = dirname(__DIR__);
$app = $root . '/app';

$fail = 0;
$total = 0;

function check(string $label, bool $condition): void
{
    global $fail, $total;

    $total++;

    if ($condition) {
        echo "ok   $label\n";
    } else {
        echo "FAIL $label\n";
        $fail++;
    }
}

$header = file_get_contents($app . '/components/AppHeader.vue');
$footer = file_get_contents($app . '/components/AppFooter.vue');
$site = file_get_contents($app . '/utils/site.js');

// ---------------------------------------------------------------------------
// The navbar
// ---------------------------------------------------------------------------

echo "== the navbar ==\n";

check('nav is fixed to the top', strpos($header, 'fixed inset-x-0 top-0') !== false);
check('nav shows the logo', strpos($header, '/_sitc/images/logo.webp') !== false);
check('nav shows the wordmark', strpos($header, 'softnet-wordmark.png') !== false);

// Every link is a NuxtLink. "News" was once a plain <a> because /news belonged
// to the PHP side; now it is a page in this app like any other, and keeping it
// as an anchor would cause a full reload for nothing.
foreach ([["'/'", 'Home'], ["'/about'", 'About'], ["'/news'", 'News'], ["'/contact'", 'Contact']] as [$path, $label]) {
    check("nav lists $label", strpos($header, "to: $path") !== false);
}

check('nav uses NuxtLink, not a plain anchor', strpos($header, '<NuxtLink') !== false);
check('no link is marked external any more', strpos($header, 'external') === false);

// The News link must stay lit for every page inside the section, or it goes
// blank the moment a reader opens a story.
check(
    'the News link stays active on /news/... pages',
    strpos($header, "startsWith('/news/')") !== false
);

check('the hamburger is described to screen readers', strpos($header, 'aria-expanded') !== false);
check('the drawer has a backdrop to tap', strpos($header, 'bg-black/40') !== false);
check('the drawer slides in', strpos($header, 'translate-x-full') !== false);

// ---------------------------------------------------------------------------
// The footer
// ---------------------------------------------------------------------------

echo "\n== the footer ==\n";

check('footer is dark', strpos($footer, 'bg-gray-900') !== false);

// The contact details are the part most likely to drift, because they are the
// only place an address or a number appears in full.
foreach ([
    '(047) 461-4145',
    '(047) 275-0842',
    'info@softnetitc.edu.ph',
    'Mariveles',
] as $detail) {
    check("footer shows $detail", strpos($site, $detail) !== false);
}

check('footer pulls its details from site.js', strpos($footer, 'site.') !== false);

// The list-bullet trap: the PHP side never imports Tailwind's reset, so a <ul>
// kept the browser's own bullet. list-none is what removes it. The Nuxt build
// does ship the reset, but the class is kept so both halves read the same and
// nothing changes if the reset is ever narrowed.
check('footer lists carry list-none', strpos($footer, 'list-none') !== false);

// ---------------------------------------------------------------------------
// One source of truth
// ---------------------------------------------------------------------------

echo "\n== one navbar, one footer ==\n";

// The whole point of moving the news pages: these hand-copies must be gone.
$phpNav = $root . '/php-backend/includes/nav.php';
$phpFooter = $root . '/php-backend/includes/footer.php';
check('the PHP navbar copy is gone', !is_file($phpNav));
check('the PHP footer copy is gone', !is_file($phpFooter));

// The PHP pages built their navbar with its own compiled Tailwind. With the
// public pages in Vue, that second build must be gone too — it was the only
// user, and a second Tailwind build is exactly the drift being removed.
check('the second Tailwind source is gone', !is_file($root . '/tailwind/php-chrome.css'));

// site.css stays: the admin still loads it. Only the public-only rules were
// trimmed out of it.
check('site.css is kept for the admin', is_file($root . '/php-backend/assets/css/site.css'));
check('admin.css is kept', is_file($root . '/php-backend/assets/css/admin.css'));
check(
    'the admin still loads site.css',
    strpos(file_get_contents($root . '/php-backend/includes/admin-header.php'), 'assets/css/site.css') !== false
);

echo "\n";
echo $fail === 0 ? "ALL $total CHECKS PASSED\n" : "$fail OF $total CHECK(S) FAILED\n";
exit($fail === 0 ? 0 : 1);
