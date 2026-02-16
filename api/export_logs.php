<?php
// api/export_logs.php

declare(strict_types=1);

$cfg = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

$u = require_login($cfg);
$isAdmin = ($u['role']==='admin');
$db = db($cfg);

$type = trim((string)($_GET['type'] ?? ''));
$result = trim((string)($_GET['result'] ?? ''));
$accountId = (int)($_GET['account_id'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));

$where = [];
$params = [];
if (!$isAdmin) { $where[]='user_id=:uid'; $params['uid']=(int)$u['id']; }
if ($type !== '') { $where[]='action_type=:t'; $params['t']=$type; }
if ($result !== '') { $where[]='result=:r'; $params['r']=$result; }
if ($accountId) { $where[]='account_id=:aid'; $params['aid']=$accountId; }
if ($q !== '') { $where[]='(domain LIKE :q OR action LIKE :q OR message LIKE :q)'; $params['q']='%'.$q.'%'; }
$wsql = $where ? ('WHERE '.implode(' AND ', $where)) : '';
$rows = db_all($db, 'SELECT * FROM logs '.$wsql.' ORDER BY id DESC', $params);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="logs_' . date('Ymd_His') . '.csv"');

// 输出 UTF-8 BOM，防止 Excel 打开时中文乱码
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');
fputcsv($out, ['time','user_id','account_id','domain','type','action','result','message','ip','ua']);
foreach($rows as $r){
    fputcsv($out, [
        date('Y-m-d H:i:s', (int)$r['created_at']),
        $r['user_id'],
        $r['account_id'],
        $r['domain'],
        $r['action_type'],
        $r['action'],
        $r['result'],
        $r['message'],
        $r['ip'],
        $r['ua'],
    ]);
}
fclose($out);
