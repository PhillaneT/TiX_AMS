<?php
/**
 * Replit landing page for the AjanaNova Grader Moodle plugin.
 *
 * IMPORTANT: This file is NOT part of the Moodle plugin proper. It only
 * runs when the plugin directory is served directly by a web server
 * (e.g., the Replit dev preview), where there is no Moodle bootstrap.
 *
 * Inside a real Moodle installation the plugin lives at
 * `<moodle>/local/ajananova/` and Moodle never invokes this index.php —
 * Moodle calls memo.php and mark.php directly, both of which require
 * Moodle's config.php and define MOODLE_INTERNAL.
 *
 * This page provides a developer overview: plugin metadata, configurable
 * settings, database schema, file layout, and installation instructions.
 */

// If Moodle has somehow loaded this file, do nothing — never serve the
// landing page from inside a Moodle install.
if (defined('MOODLE_INTERNAL')) {
    return;
}

// Pull plugin metadata directly from version.php without requiring Moodle.
$plugin = new stdClass();
$pluginPath = __DIR__ . '/version.php';
if (is_readable($pluginPath)) {
    $contents = file_get_contents($pluginPath);
    foreach ([
        'component' => "/\\\$plugin->component\s*=\s*'([^']+)'/",
        'version'   => "/\\\$plugin->version\s*=\s*([0-9]+)/",
        'requires'  => "/\\\$plugin->requires\s*=\s*([0-9]+)/",
        'maturity'  => "/\\\$plugin->maturity\s*=\s*([A-Z_]+)/",
        'release'   => "/\\\$plugin->release\s*=\s*'([^']+)'/",
    ] as $key => $re) {
        if (preg_match($re, $contents, $m)) {
            $plugin->{$key} = $m[1];
        }
    }
}

$composer = json_decode(@file_get_contents(__DIR__ . '/composer.json'), true) ?: [];

// Walk the project tree (skip vendor/, .git/, .local/, .cache/, .agents/).
$skipDirs = ['vendor', '.git', '.local', '.cache', '.agents'];
function tree_dir(string $path, array $skip, string $rel = '', int $depth = 0): array {
    $items = [];
    if ($depth > 4) {
        return $items;
    }
    $entries = @scandir($path);
    if (!$entries) {
        return $items;
    }
    sort($entries);
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || $entry[0] === '.') {
            continue;
        }
        $full = $path . '/' . $entry;
        $r = $rel === '' ? $entry : $rel . '/' . $entry;
        if (is_dir($full)) {
            if (in_array($entry, $skip, true)) {
                continue;
            }
            $items[] = ['type' => 'dir', 'name' => $entry, 'path' => $r, 'depth' => $depth];
            foreach (tree_dir($full, $skip, $r, $depth + 1) as $sub) {
                $items[] = $sub;
            }
        } else {
            $items[] = ['type' => 'file', 'name' => $entry, 'path' => $r, 'depth' => $depth, 'size' => @filesize($full)];
        }
    }
    return $items;
}
$tree = tree_dir(__DIR__, $skipDirs);

// Parse install.xml for table summaries.
$tables = [];
$xmlPath = __DIR__ . '/db/install.xml';
if (is_readable($xmlPath)) {
    $xml = @simplexml_load_file($xmlPath);
    if ($xml && isset($xml->TABLES->TABLE)) {
        foreach ($xml->TABLES->TABLE as $t) {
            $fields = [];
            foreach ($t->FIELDS->FIELD as $f) {
                $fields[] = [
                    'name'    => (string)$f['NAME'],
                    'type'    => (string)$f['TYPE'],
                    'length'  => (string)$f['LENGTH'],
                    'notnull' => (string)$f['NOTNULL'] === 'true',
                    'default' => (string)$f['DEFAULT'],
                ];
            }
            $tables[] = [
                'name'    => (string)$t['NAME'],
                'comment' => (string)$t['COMMENT'],
                'fields'  => $fields,
            ];
        }
    }
}

// Pull setting keys + descriptions out of settings.php (regex, no Moodle).
$settings = [];
$settingsSrc = @file_get_contents(__DIR__ . '/settings.php') ?: '';
if (preg_match_all(
    "/'local_ajananova\\/([a-z_]+)'/i",
    $settingsSrc,
    $m
)) {
    $settings = array_values(array_unique($m[1]));
}

$composerDeps = $composer['require'] ?? [];

$h = static function ($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $h($plugin->component ?? 'AjanaNova Grader') ?> — Moodle plugin</title>
<style>
  :root {
    --bg: #0b1020;
    --panel: #11172c;
    --panel-2: #161d36;
    --text: #e6ebff;
    --muted: #9aa3c7;
    --accent: #7aa2ff;
    --accent-2: #9b87ff;
    --ok: #6ee7a8;
    --warn: #ffb86b;
    --border: #232a4a;
    --code-bg: #0a0f22;
  }
  * { box-sizing: border-box; }
  html, body { background: var(--bg); color: var(--text); margin: 0; }
  body {
    font: 15px/1.55 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
          Oxygen, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
  }
  .wrap { max-width: 1100px; margin: 0 auto; padding: 32px 24px 80px; }
  header { padding: 28px 0 16px; border-bottom: 1px solid var(--border); }
  h1 { margin: 0 0 6px; font-size: 28px; letter-spacing: -0.01em; }
  h2 { font-size: 18px; margin: 32px 0 12px; color: var(--accent); letter-spacing: .02em; text-transform: uppercase; }
  .sub { color: var(--muted); }
  .badges { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
  .badge {
    display: inline-block; padding: 4px 10px; border-radius: 999px;
    background: var(--panel-2); border: 1px solid var(--border);
    color: var(--text); font-size: 12px;
  }
  .badge.alpha { color: var(--warn); border-color: rgba(255,184,107,.4); }
  .badge.ok    { color: var(--ok); border-color: rgba(110,231,168,.4); }
  .grid { display: grid; gap: 16px; grid-template-columns: 1fr; }
  @media (min-width: 800px) { .grid.two { grid-template-columns: 1fr 1fr; } }
  .card {
    background: var(--panel); border: 1px solid var(--border);
    border-radius: 12px; padding: 18px 20px;
  }
  .card h3 { margin: 0 0 10px; font-size: 15px; color: var(--text); }
  .kv { display: grid; grid-template-columns: max-content 1fr; gap: 4px 14px; font-size: 14px; }
  .kv dt { color: var(--muted); }
  .kv dd { margin: 0; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
  ul.clean { list-style: none; padding: 0; margin: 0; }
  ul.clean li { padding: 6px 0; border-bottom: 1px dashed var(--border); }
  ul.clean li:last-child { border-bottom: 0; }
  code, pre {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 13px;
  }
  pre {
    background: var(--code-bg); border: 1px solid var(--border);
    padding: 14px 16px; border-radius: 10px; overflow-x: auto;
  }
  .tree { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; }
  .tree .row { display: flex; align-items: center; gap: 8px; padding: 2px 0; }
  .tree .name.dir  { color: var(--accent); }
  .tree .name.file { color: var(--text); }
  .tree .size { margin-left: auto; color: var(--muted); font-size: 11px; }
  table { border-collapse: collapse; width: 100%; font-size: 13px; }
  th, td {
    text-align: left; padding: 6px 8px;
    border-bottom: 1px solid var(--border);
  }
  th { color: var(--muted); font-weight: 500; }
  .field-pk { color: var(--accent-2); font-weight: 600; }
  .notice {
    background: linear-gradient(180deg, rgba(122,162,255,.08), rgba(122,162,255,.02));
    border: 1px solid rgba(122,162,255,.25);
    padding: 14px 16px; border-radius: 10px; color: var(--text);
  }
  a { color: var(--accent); text-decoration: none; }
  a:hover { text-decoration: underline; }
</style>
</head>
<body>
  <div class="wrap">
    <header>
      <h1>AjanaNova Grader</h1>
      <div class="sub">Moodle local plugin — AI-assisted assessment marking, credit billing, and PDF annotation.</div>
      <div class="badges">
        <span class="badge"><?= $h($plugin->component ?? '—') ?></span>
        <span class="badge">v<?= $h($plugin->release ?? '—') ?> · build <?= $h($plugin->version ?? '—') ?></span>
        <span class="badge alpha"><?= $h(strtolower(str_replace('MATURITY_', '', $plugin->maturity ?? ''))) ?: 'alpha' ?></span>
        <span class="badge ok">PHP <?= $h(PHP_VERSION) ?></span>
        <span class="badge ok">composer deps installed</span>
      </div>
    </header>

    <h2>About this preview</h2>
    <div class="notice">
      <strong>This is not a standalone web app.</strong> AjanaNova Grader is a
      Moodle <em>local plugin</em> that lives at
      <code>&lt;moodle&gt;/local/ajananova/</code>. The PHP files
      (<code>memo.php</code>, <code>mark.php</code>, every class) bootstrap
      Moodle via <code>require_once(__DIR__ . '/../../config.php');</code>
      and call <code>defined('MOODLE_INTERNAL') || die();</code>, so they
      cannot render outside a Moodle install. This page is a developer
      overview served by PHP's built-in server so the project is browsable
      in the Replit preview.
    </div>

    <h2>Plugin metadata</h2>
    <div class="card">
      <dl class="kv">
        <dt>Component</dt><dd><?= $h($plugin->component ?? '—') ?></dd>
        <dt>Release</dt><dd><?= $h($plugin->release ?? '—') ?></dd>
        <dt>Version</dt><dd><?= $h($plugin->version ?? '—') ?></dd>
        <dt>Requires Moodle</dt><dd><?= $h($plugin->requires ?? '—') ?> (Moodle 4.1+)</dd>
        <dt>Maturity</dt><dd><?= $h($plugin->maturity ?? '—') ?></dd>
        <dt>License</dt><dd><?= $h($composer['license'] ?? 'GPL-3.0-or-later') ?></dd>
      </dl>
    </div>

    <h2>Composer dependencies</h2>
    <div class="card">
      <ul class="clean">
        <?php foreach ($composerDeps as $name => $constraint): ?>
          <li><code><?= $h($name) ?></code> <span class="sub"><?= $h($constraint) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <h2>Admin settings (<code>settings.php</code>)</h2>
    <div class="card">
      <ul class="clean">
        <?php foreach ($settings as $s): ?>
          <li><code>local_ajananova/<?= $h($s) ?></code></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <h2>Database schema (<code>db/install.xml</code>)</h2>
    <div class="grid two">
      <?php foreach ($tables as $t): ?>
        <div class="card">
          <h3><code><?= $h($t['name']) ?></code></h3>
          <div class="sub" style="margin-bottom:10px;"><?= $h($t['comment']) ?></div>
          <table>
            <thead><tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr></thead>
            <tbody>
              <?php foreach ($t['fields'] as $f): ?>
                <tr>
                  <td class="<?= $f['name'] === 'id' ? 'field-pk' : '' ?>"><?= $h($f['name']) ?></td>
                  <td><?= $h($f['type']) ?><?= $f['length'] !== '' ? '(' . $h($f['length']) . ')' : '' ?></td>
                  <td><?= $f['notnull'] ? 'no' : 'yes' ?></td>
                  <td><?= $h($f['default'] !== '' ? $f['default'] : '—') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endforeach; ?>
    </div>

    <h2>Project layout</h2>
    <div class="card tree">
      <?php foreach ($tree as $n): ?>
        <div class="row" style="padding-left: <?= 12 * (int)$n['depth'] ?>px;">
          <span><?= $n['type'] === 'dir' ? '▸' : '·' ?></span>
          <span class="name <?= $n['type'] ?>"><?= $h($n['name']) ?><?= $n['type'] === 'dir' ? '/' : '' ?></span>
          <?php if ($n['type'] === 'file' && isset($n['size'])): ?>
            <span class="size"><?= number_format($n['size']) ?> B</span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <h2>Installing in Moodle</h2>
    <div class="card">
      <ol style="padding-left: 20px; margin: 0;">
        <li>Copy this directory to <code>&lt;moodle&gt;/local/ajananova/</code>.</li>
        <li>Run <code>composer install</code> inside the plugin directory to vendor the PDF libraries.</li>
        <li>Visit <code>Site administration → Notifications</code> in your Moodle admin to trigger the install of the database tables defined in <code>db/install.xml</code>.</li>
        <li>Configure the plugin under <code>Site administration → Plugins → Local plugins → AjanaNova Grader</code>. Mock mode is on by default — leave it on until you have entered a real Anthropic API key.</li>
        <li>Open any assignment activity as an assessor and use the gear menu items <em>AjanaNova: Upload marking guide</em> and <em>AjanaNova: Mark with AI</em>.</li>
      </ol>
    </div>
  </div>
</body>
</html>
