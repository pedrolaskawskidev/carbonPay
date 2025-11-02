<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

# 1. Clone o repositório
git clone https://github.com/pedrolaskawskidev/carbonPay.git
cd carbon

# 2. Instale as dependências PHP
composer install

# 4. Configure o ambiente
cp .env.example .env

# 5. Gere a chave da aplicação
php artisan key:generate

# 6. Crie o banco de dados e rode as migrações
php artisan migrate --seed

Aplicação usa API do ChatGPT, necessário inserir suas credenciais, ou caso queria acesse a solução completa aqui: https://carbon-pay-ndhb.vercel.app/

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
