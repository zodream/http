# http

优雅链式调用API

## 示例

引用

```php

use Zodream\Http\Http;

```

获取指定网址内容

```php

(new Http('https://zodream.cn'))->get();

```

传递GET 参数

```php

(new Http())->url('https://zodream.cn', [
    'page' => 1
])->get();

```

传递 POST

```php

(new Http('https://zodream.cn'))->maps([
    'page' => 1
])->text();

```

## 高级用法

自动进行值的填充

```php

(new Http())->url('https://zodream.cn', [
    'keywords'
])->maps([
    'page' => 1,     // 这是使用默认值
    '#url'           // 如果没有会报错
])->parameters([
    'url' => 'aaaa',
    'keywords' => 'q',
    'aa' => 'bb'        // 这个并不会传递过去
])->encode(Http::JSON)->decode(Http::Json)->decode(function($data) {
    $data['a'] = $data['data'];
    return $data;
})->text();

```

相当于

请求 https://zodream.cn/?keywords=q

POST {"page":1, "url":"aaaa"}

获取的结果是 {"code":200, "data":"a"}

最后你接收到的是数组
```php

[
   'code' => 200,
   'data' => 'a',
   'a'    => 'a' 
]

```


## 值自动匹配

```php

$data = Http::getMapParameters([
    '#goods_id',              // # 必须有值 否则报错
    'a:f' => [                // 如果 f 有值 传给 a 即 a => 2，如果无值, 进行子匹配  a => [e => 11]
        '#e'
    ],
    [              // 多个里面匹配至少一个
        'b',
        'c',
        'd'
    ],
    'g' => 1                 // 可以设置默认值， 数组不是默认值
], [
    'goods_id' => 1,
    'f' => 2,
    'c' => 4,
    'd' => 7,
    'e' => 11
]);

```