# VK API Wrapper

The PHP class for vk.com API and OAuth.

Features: HTTP proxy, captcha recognition via antigate, api response timeout limit

### Use
1. Install via composer

        composer require s-anasol/vk

2. Create VK object
    1. without authorization

            $vk = new VK\VK('{APP_ID}', '{API_SECRET}');

    2. with authorization

            $vk = new VK\VK('{APP_ID}', '{API_SECRET}', '{ACCESS_TOKEN}');

3. If need authorization
    1. Get authoriz link

            $vk->getAuthorizeURL('{API_SETTINGS}', '{CALLBACK_URL}');

    2. Get the token access by code from the authoriz link

            $vk->getAccessToken('{CODE}');

    3. Check the status of authorization

            $vk->isAuth(); // return bool

4. Usage API

        $vk->api('{METHOD_NAME}', '{PARAMETERS}');

5. Captcha recognition

  ```
  $vk->setAntigate('antigate-key');
  $vk->setCaptchaSavePath('./captcha'); // YOU MUST create 'captcha' folder at project root path
  ```

### Class methods
* `$vk->setApiVersion({NUBMER});` - set api version
* `$vk->setProxy("1.2.3.4:8080");` - set HTTP proxy for API requests
* `$vk->setTimeout(10);` - set API Response timeout
* `$vk->setAntigate("antigate-key");` - set [antigate](http://antigate.com/) API key to resolve captcha
* `$vk->setCaptchaSavePath("/tmp");` - set temporary captcha image save path

### Variables
* `{APP_ID}` — Your application's identifier.
* `{API_SECRET}` — Secret application key.
* `{ACCESS_TOKEN}` — Access token.
* `{API_SETTINGS}` —  Access [rights requested](http://vk.com/developers.php?oid=-17680044&p=Application_Access_Rights) by your app (through comma).
* `{CALLBACK_URL}` —  Address to which `{CODE}` will be rendered.
* `{CODE}` — The code to get access token.
* `{METHOD_NAME}` — Name of the API method. [All methods.](http://vk.com/developers.php?oid=-17680044&p=API_Method_Description)
* `{PARAMETERS}` — Parameters of the corresponding API methods.

\* If you need infinite token use key `offline` in `{API_SETTINGS}`.

### License
[MIT](https://raw.github.com/vladkens/VK/master/LICENSE)
