# Contributing to KYC Secure Upload (PrestaShop module)

KYC Secure Upload is an open-source PrestaShop module. Everyone is welcome—and encouraged!—to contribute improvements, bug fixes, and translations.

This module follows the PrestaShop ecosystem’s best practices and is written mostly in PHP, with JavaScript, HTML, CSS, Smarty/Twig, and SQL.

---

## How to contribute

- **Bug reports:**  
  Open a [GitHub Issue](../../issues) with as much detail as possible.
- **Feature requests:**  
  Please open an Issue first to discuss new ideas before submitting a PR.
- **Pull requests:**  
  1. Fork the repository  
  2. Create your feature branch (`git checkout -b feature/my-feature`)  
  3. Commit your changes (`git commit -am 'Add new feature'`)  
  4. Push your branch (`git push origin feature/my-feature`)  
  5. Open a Pull Request against the `main` branch

**Tip:** One PR per feature or bug fix.

---

## Code standards

- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) and PrestaShop [module best practices](https://devdocs.prestashop-project.org/8/modules/creation/good-practices/).
- Write clear PHPDoc comments and use English for code and documentation.
- Add or update tests if relevant.
- Update documentation (README) if your change affects usage.

---

## Branching model

- All changes should be based on the `main` branch (or `develop` if it exists).
- Bug fixes: `fix/short-description`
- Features: `feature/short-description`

---

## Testing

You can test the module using the provided `docker-compose.yml` file in the root directory. Run `docker-compose up` to set up a local PrestaShop environment for testing.

### Back office access information

The default url/credentials to access to PrestaShop's back office defined in [`https://github.com/PrestaShop/prestashop-flashlight/blob/main/assets/hydrate.sh`](https://github.com/PrestaShop/prestashop-flashlight/blob/main/assets/hydrate.sh) and are set to:

| Url      | {PS_DOMAIN}/admin-dev |
| -------- | --------------------- |
| Login    | admin@prestashop.com  |
| Password | prestashop            |

---

## License

All files in this module are released under the [MIT License](LICENSE).

---

## Resources

- [PrestaShop Module Development Docs](https://devdocs.prestashop-project.org/8/modules/)
- [PrestaShop Flashlight](https://github.com/PrestaShop/prestashop-flashlight)
- [GitHub Help](https://help.github.com/)
- [Try Git](https://try.github.io/)

---

Thank you for helping to make this module better for everyone!

