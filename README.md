# Simple PDO Wrapper for SMF
![SMF 2.1](https://img.shields.io/badge/SMF-2.1-ed6033.svg?style=flat)
![License](https://img.shields.io/github/license/dragomano/simple-pdo-wrapper-for-smf)
![PHP](https://img.shields.io/badge/PHP-^7.4-blue.svg?style=flat)

* **Author:** Bugo
* **License:** [MIT](https://github.com/dragomano/Simple-PDO-Wrapper-for-SMF/blob/main/LICENSE)
* **Compatible with:** SMF 2.1 RC4+ / PHP 7.4+
* **Hooks only:** Yes

## Description
Work with database in SMF as in Laravel.

## How to use

```php
$db = \Bugo\PDOSMF\Database::getInstance();
```

## Example of code

### You can use convenient commands with a fluid interface
Almost all commands are equivalent to similar commands in Laravel Eloquent:

```php
$db->table('members')->orderBy('id_member', 'desc')->groupBy('id_member', 'real_name')->get();
$db->table('members')->limit(2)->get();
$db->table('members')->pluck('id_member', 'real_name');
$db->table('members')->insert(['real_name' => 'Test', 'buddy_list' => '', 'signature' => '', 'ignore_boards' => '']);
$db->table('members')->where('id_member', 5)->update(['real_name' => 'Test']);
$db->table('members')->where('id_member', 26)->decrement('posts', 1, ['real_name' => 'Test']);
$db->table('members')->find(1, 'id_member', ['real_name']);
$db->table('members AS mem')->leftJoin('topics AS t', 't.id_member_started = mem.id_member')->where('mem.id_member', 1)->get();
$db->table('members')->whereIn('id_member', [1, 26])->get();
$db->table('members')->whereRaw('id_member > :id_member', ['id_member' => 10])->get();
```

### Removing of entries

```php
$db->table('members')->where('id_member', 57)->delete();
```

### Getting text of queries

```php
var_dump($db->getQueries());
```

### Example of a complex query

```php
$request = $db->table('lp_pages AS p')
    ->select('p.page_id, p.author_id, p.alias, p.content, p.description, p.type, p.status, p.num_views, p.num_comments, p.created_at')
    ->addSelect('GREATEST(p.created_at, p.updated_at) AS date, mem.real_name AS author_name')
    ->selectRaw('(SELECT lp_com.created_at FROM {db_prefix}lp_comments AS lp_com WHERE p.page_id = lp_com.page_id ORDER BY lp_com.created_at DESC LIMIT 1) AS comment_date')
    ->selectRaw('(SELECT lp_com.author_id FROM {db_prefix}lp_comments AS lp_com WHERE p.page_id = lp_com.page_id ORDER BY lp_com.created_at DESC LIMIT 1) AS comment_author_id')
    ->selectRaw('(SELECT real_name FROM {db_prefix}lp_comments AS lp_com LEFT JOIN {db_prefix}members ON (lp_com.author_id = id_member) WHERE lp_com.page_id = p.page_id ORDER BY lp_com.created_at DESC LIMIT 1) AS comment_author_name')
    ->selectRaw('(SELECT lp_com.message FROM {db_prefix}lp_comments AS lp_com WHERE p.page_id = lp_com.page_id ORDER BY lp_com.created_at DESC LIMIT 1) AS comment_message')
    ->leftJoin('members AS mem', 'p.author_id = mem.id_member')
    ->where('p.status', $custom_parameters['status'])
    ->where('p.created_at', '<=', $custom_parameters['current_time'])
    ->whereIn('p.permissions', $custom_parameters['permissions'])
    ->orderBy($custom_sorting[$modSettings['lp_frontpage_article_sorting'] ?? 0])
    ->limit($custom_parameters['start'], $custom_parameters['limit'])
    ->get();
```
