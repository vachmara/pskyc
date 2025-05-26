# Copilot Instructions for PrestaShop 8 Module Development

This file guides AI code assistants and developers to generate, auto-complete, or review code for PrestaShop 8 modules. All content is based on [PrestaShop's official documentation](https://devdocs.prestashop-project.org/8/).

---

## 1. Module File & Folder Structure

- **Module folder:** Place your module in `/modules/<modulename>`. The folder and main file must have the same lowercase name (e.g. `mymodule/mymodule.php`).
- **Class:** Main class must be `PascalCase`, match the module name, and extend `Module` or a specialized subclass.
- **Recommended structure:**

    ```
    mymodule/
    ├── config/
    │   ├── services.yml
    │   ├── admin/services.yml
    │   └── front/services.yml
    ├── controllers/
    │   ├── admin/
    │   └── front/
    ├── src/                   # Modern PHP classes, Symfony controllers
    ├── override/              # Overrides (use sparingly)
    ├── translations/
    ├── upgrade/
    ├── vendor/                # Composer dependencies
    ├── views/
    │   ├── css/, js/, img/
    │   └── templates/
    │       ├── admin/
    │       ├── front/
    │       └── hook/
    ├── config.xml
    ├── logo.png
    └── mymodule.php
    ```

---

## 2. Main Module Class Example

```php
<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class MyModule extends Module
{
    public function __construct()
    {
        $this->name = 'mymodule';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Firstname Lastname';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '8.0.0',
            'max' => '8.99.99'
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('My module', [], 'Modules.Mymodule.Admin');
        $this->description = $this->trans('Description of my module.', [], 'Modules.Mymodule.Admin');
        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall?', [], 'Modules.Mymodule.Admin');
    }

    public function install()
    {
        return parent::install() &&
            $this->registerHook('displayHeader');
    }

    public function uninstall()
    {
        return parent::uninstall();
    }
}
```

## 3. Registering and Using Hooks

- **Register hooks** in the `install()` using `$this->registerHook('hookName')`.
- **Implement hooks** by creating methods like `public function hookDisplayHeader($params) { ... }`.
- **Display hooks** return HTML (string or via `$this->display()` and templates).
- **Action hooks** perform logic, no output needed.

## 4. Back Office Controllers

- Legacy (controllers/admin/):
  - Class name: <ModuleClassName><ControllerName>ModuleAdminController.
  - Extend ModuleAdminController.
  - Create a Tab in the BO via the Tab class.

- Symfony (src/Controller):

  - Extend PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController.
  - Define routes in config/routes.yml or use @Route annotations.
  - Render Twig templates in views/templates/admin/.

Example Symfony Admin Controller:
```php
<?php>
namespace MyCompany\MyModule\Controller;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\Routing\Annotation\Route;

class DemoController extends FrameworkBundleAdminController
{
    /**
     * @Route("/demo", name="my_module_demo")
     */
    public function demoAction()
    {
        return $this->render('@Modules/mymodule/templates/admin/demo.html.twig', [
            'customMessage' => 'Hello from My Module'
        ]);
    }
}
```

## 5. Front Office Controllers
- Legacy (controllers/front/):
  - Class name: <ModuleClassName><ControllerName>ModuleFrontController.
  - Extend ModuleFrontController.
  - Use `$this->setTemplate()` to set the template.

Example:
```php
<?php
class MyModuleDisplayModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
        $this->context->smarty->assign('msg', 'Hello, customer!');
        $this->setTemplate('module:mymodule/views/templates/front/display.tpl');
    }
}
```

## 6. Symfony Services & Dependency Injection
- Define services in `config/services.yml`.
  ```yaml
  services:
    _defaults:
      public: true
    mymodule.my_service:
      class: MyCompany\MyModule\MyService
      arguments:
        - '@translator'
  ```
- Use `src/` with namespaces and PSR-4.
- Access services in Symfony controllers via `$this->get('mymodule.my_service')`.

# 7. Composer & Autoloading
- Add a `composer.json` file in the module root:
```json
{
  "name": "your-vendor/mymodule",
  "type": "prestashop-module",
  "autoload": {
    "psr-4": {
      "MyCompany\\MyModule\\": "src/"
    },
    "classmap": ["mymodule.php"]
  },
  "config": {
    "prepend-autoloader": false
  }
}
```
- Run `composer install` and include `vendor/` in your moduke package.
- Never overwrite PrestaShop's oown autoloader.

# 8. Translations

- Use `$this->trans('String', [], 'Modules.Mymodule.Admin')` in PHP.
- Use `{l s='String' d='Modules.Mymodule.Admin'}` in Smarty.
- Use `{{ 'String'|trans({}, 'Modules.Mymodule.Admin') }}` in Twig.
- Implement `isUsingNewTranslationSystem()` to enable PrestaShop 8's translation system.
- Provide XLF files in `translations/` for shipped translations.

## 9. Security Best Practices

- Add an `index.php` with a redirect in each folder to prevent directory listing.
- Validate and sanitize all input; use PrestaShop’s `Validate` class.
- Use tokens for form submissions.
- **Avoid raw SQL; use PrestaShop DB helpers and proper escaping.**

## 10. Compatibility & Upgrades

- Declare supported PrestaShop versions with `$this->ps_versions_compliancy`.
- Provide upgrade scripts in `upgrade/` for database.schema changes.
- Test your module on all versions you support.
- Avoid core overrides unless absolutely necessary.