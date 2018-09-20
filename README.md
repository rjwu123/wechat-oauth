## Overview
微信OAuth登录

## Installation
#### Composer (推荐)
把下面的配置加入你的`composer.json`文件
```json
"rjwu123/wechat-oauth": "dev-master"
```
然后使用[Composer](https://getcomposer.org/)来安装SDK
```bash
composer install
```

## Usage
#### Autoload
如果你用Composer来安装，可以用以下代码自动加载
```php
require 'vendor/autoload.php';
```
SDK位于全局命名空间下。
```php
use rjwu123\wechat\Oauth
```

#### Initialization
实例化`OAuth`即可完成初始化
```php
$oauth = new \rjwu123\wechat\OAuth($options);
```
`$appid`和`$secret`是微信开放平台的应用的唯一标识和秘钥AppSecret

#### Code samples
```php
require 'vendor/autoload.php';

use rjwu123\wechat\Oauth;

$oauth = new Oauth([
    'appid' => 'xxxx',
    'appsecret' => 'xxxx',
    'redirectUri' => 'http://www.test.com',
    'scope' => 'snsapi_userinfo'
        ]);

// 第一步：用户同意授权，获取code
$authUrl = $oauth->getAuthorizationUrl();
if (!isset($_GET['code'])) {
    $oauth->redirect($authUrl);
} else {
    // 第二步：通过code换取网页授权access_token
    $res = $oauth->getAccessToken('authorization_code', [
        'code' => $_GET['code']
    ]);
    if (isset($res['errcode']) && $res['errcode'] > 0) {
        $oauth->redirect($authUrl);
        return;
    } else {
        // 第四步：拉取用户信息(需scope为 snsapi_userinfo)
        if ($res['scope'] == 'snsapi_userinfo') {
            $info = $oauth->getUserInfo($res);
        }
    }

    // 第三步：刷新access_token
    $url = $oauth->getAccessToken('refresh_token', [
        'refresh_token' => $res['refresh_token']
    ]);
}
```
