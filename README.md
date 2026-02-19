# Symfony Multi-Database RESTful API

A Symfony 7 microservices architecture with multi-database support (MySQL, SQL Server, Redis, DynamoDB) integrated with AWS Secrets Manager for secure credential management.

## 🏗️ Architecture

### Core Components

- **Connection Factory Pattern**: Centralized database connection management
- **AWS Secrets Manager Integration**: Secure credential storage with Redis caching
- **Multi-Database Support**: MySQL, SQL Server, Redis, and DynamoDB
- **Bounded Contexts**: Separate modules for Users (MySQL) and Books (SQL Server)
- **SOLID Principles**: Clean architecture with Domain-Driven Design

### Tech Stack

- **Framework**: Symfony 7.1 (PHP 8.3)
- **Databases**: MySQL 8.0, SQL Server 2022
- **Cache/Session**: Redis 7
- **NoSQL**: DynamoDB (AWS)
- **ORM**: Doctrine (Entity Manager for writes, DBAL for complex reads)
- **Containerization**: Docker + Docker Compose
- **Cloud**: AWS Secrets Manager

## 📁 Project Structure

```
api-restful/
├── config/
│   ├── packages/
│   │   ├── doctrine.yaml          # Multi-database configuration
│   │   └── framework.yaml
│   ├── services.yaml               # DI container configuration
│   └── routes.yaml
├── docker/
│   ├── mysql/
│   ├── sqlserver/
│   ├── nginx/
│   └── php/
├── src/
│   ├── Shared/                     # Shared Kernel
│   │   ├── Domain/
│   │   │   └── Contracts/
│   │   │       └── DatabaseConnectionInterface.php
│   │   └── Infrastructure/
│   │       ├── AWS/
│   │       │   ├── SecretsManagerService.php
│   │       │   └── SecretsCache.php
│   │       └── Database/
│   │           ├── ConnectionFactory.php
│   │           ├── ConnectionManager.php
│   │           ├── Connectors/
│   │           │   ├── MySQLConnector.php
│   │           │   ├── SQLServerConnector.php
│   │           │   ├── RedisConnector.php
│   │           │   └── DynamoDBConnector.php
│   │           └── Exceptions/
│   ├── User/                       # Users Bounded Context (MySQL)
│   │   ├── Domain/
│   │   ├── Application/
│   │   └── Infrastructure/
│   └── Book/                       # Books Bounded Context (SQL Server)
│       ├── Domain/
│       ├── Application/
│       └── Infrastructure/
├── docker-compose.yml
├── composer.json
└── .env
```

## 🚀 Getting Started

### Prerequisites

- Docker Desktop installed
- AWS Account (free tier is sufficient)
- AWS CLI configured (optional but recommended)
- Git

### 1. Clone and Setup

```bash
cd /Users/3shumac/Documents/apps/php/api-restful
```

### 2. Configure Environment

Edit `.env.local` file (create if not exists):

```bash
# For local development without AWS
USE_AWS_SECRETS=false

# For AWS Secrets Manager integration
USE_AWS_SECRETS=true
AWS_REGION=us-east-1
AWS_ACCESS_KEY_ID=your_actual_key_here
AWS_SECRET_ACCESS_KEY=your_actual_secret_here
```

### 3. Build and Start Docker Containers

```bash
# Build and start all services
docker-compose up -d --build

# Check container status
docker-compose ps

# View logs
docker-compose logs -f php
```

### 4. Install PHP Dependencies

```bash
# Enter PHP container
docker exec -it api-restful-php bash

# Install Composer dependencies
composer install

# Exit container
exit
```

### 5. Verify Installation

Access the application:
- **Application**: http://localhost:8080
- **MySQL**: localhost:3306
- **SQL Server**: localhost:1433
- **Redis**: localhost:6379

## 🔐 AWS Secrets Manager Setup

### Create Secrets in AWS

#### MySQL Users Secret

```bash
aws secretsmanager create-secret \
    --name api-restful/mysql_users \
    --description "MySQL credentials for Users database" \
    --secret-string '{
        "driver": "mysql",
        "host": "mysql",
        "port": 3306,
        "database": "users_db",
        "user": "root",
        "password": "mysql_secret",
        "charset": "utf8mb4"
    }' \
    --region us-east-1
```

#### SQL Server Books Secret

```bash
aws secretsmanager create-secret \
    --name api-restful/sqlserver_books \
    --description "SQL Server credentials for Books database" \
    --secret-string '{
        "driver": "sqlserver",
        "host": "sqlserver",
        "port": 1433,
        "database": "books_db",
        "user": "sa",
        "password": "SqlServer2024!",
        "trust_server_certificate": true
    }' \
    --region us-east-1
```

### Verify Secrets

```bash
# List secrets
aws secretsmanager list-secrets --region us-east-1

# Get specific secret
aws secretsmanager get-secret-value \
    --secret-id api-restful/mysql_users \
    --region us-east-1
```

## 🧪 Testing Connection Manager

Create a test command to verify all connections:

```bash
docker exec -it api-restful-php php bin/console app:test-connections
```

### Test Script (Manual Testing)

```php
<?php
// bin/test-connections.php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__ . '/../.env');

$kernel = new Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

$connectionManager = $container->get('connection_manager');

echo "Testing MySQL Connection...\n";
$mysqlConn = $connectionManager->getConnection('api-restful/mysql_users');
echo "MySQL Connected: " . ($mysqlConn->isConnected() ? 'YES' : 'NO') . "\n";

echo "\nTesting SQL Server Connection...\n";
$sqlServerConn = $connectionManager->getConnection('api-restful/sqlserver_books');
echo "SQL Server Connected: " . ($sqlServerConn->isConnected() ? 'YES' : 'NO') . "\n";

echo "\n✅ All connections successful!\n";
```

## 📊 Database Schema

### MySQL - Users Database

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(180) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);
```

### SQL Server - Books Database

```sql
CREATE TABLE books (
    id INT IDENTITY(1,1) PRIMARY KEY,
    isbn VARCHAR(13) UNIQUE NOT NULL,
    title NVARCHAR(200) NOT NULL,
    author NVARCHAR(100) NOT NULL,
    published_year INT NOT NULL,
    created_at DATETIME2 NOT NULL,
    updated_at DATETIME2 NOT NULL
);
```

## 🔧 Usage Examples

### Using Connection Manager

```php
use App\Shared\Infrastructure\Database\ConnectionManager;

class UserService
{
    public function __construct(
        private ConnectionManager $connectionManager
    ) {}

    public function findUserByEmail(string $email): ?array
    {
        $connection = $this->connectionManager
            ->getConnection('api-restful/mysql_users')
            ->getConnection(); // Get Doctrine Connection

        return $connection->executeQuery(
            'SELECT * FROM users WHERE email = ?',
            [$email]
        )->fetchAssociative();
    }
}
```

### With Entity Manager (Writes)

```php
use Doctrine\ORM\EntityManagerInterface;

class CreateUserService
{
    public function __construct(
        private EntityManagerInterface $entityManager // autowired for default EM
    ) {}

    public function execute(string $email, string $name): User
    {
        $user = new User($email, $name);
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        return $user;
    }
}
```

### With DBAL (Complex Reads)

```php
use App\Shared\Infrastructure\Database\ConnectionManager;

class BookStatisticsService
{
    public function __construct(
        private ConnectionManager $connectionManager
    ) {}

    public function getBooksByDecade(): array
    {
        $connection = $this->connectionManager
            ->getConnection('api-restful/sqlserver_books')
            ->getConnection();

        return $connection->executeQuery('
            SELECT 
                (published_year / 10) * 10 as decade,
                COUNT(*) as book_count
            FROM books
            GROUP BY (published_year / 10) * 10
            ORDER BY decade DESC
        ')->fetchAllAssociative();
    }
}
```

## 🐳 Docker Commands

```bash
# Start services
docker-compose up -d

# Stop services
docker-compose down

# Rebuild services
docker-compose up -d --build

# View logs
docker-compose logs -f [service_name]

# Access PHP container
docker exec -it api-restful-php bash

# Access MySQL
docker exec -it api-restful-mysql mysql -uroot -pmysql_secret

# Access SQL Server
docker exec -it api-restful-sqlserver /opt/mssql-tools/bin/sqlcmd \
    -S localhost -U sa -P "SqlServer2024!"

# Access Redis
docker exec -it api-restful-redis redis-cli
```

## 📝 Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `USE_AWS_SECRETS` | Enable/disable AWS Secrets Manager | `false` |
| `AWS_REGION` | AWS region for Secrets Manager | `us-east-1` |
| `AWS_ACCESS_KEY_ID` | AWS access key | - |
| `AWS_SECRET_ACCESS_KEY` | AWS secret key | - |
| `MYSQL_HOST` | MySQL hostname (local fallback) | `mysql` |
| `MYSQL_PASSWORD` | MySQL password (local fallback) | `mysql_secret` |
| `SQLSERVER_HOST` | SQL Server hostname (local fallback) | `sqlserver` |
| `SQLSERVER_PASSWORD` | SQL Server password (local fallback) | `SqlServer2024!` |
| `REDIS_HOST` | Redis hostname | `redis` |

## 🔍 Health Checks

Each database service includes health checks in docker-compose.yml:

- **MySQL**: `mysqladmin ping`
- **SQL Server**: `sqlcmd -Q "SELECT 1"`
- **Redis**: `redis-cli ping`

## 🛠️ Next Steps (Phase 3 & 4)

1. **User API (MySQL)**
   - Create User entity
   - Implement CRUD use cases
   - Build REST controllers
   - Add validation

2. **Book API (SQL Server)**
   - Create Book entity
   - Implement CRUD use cases
   - Build REST controllers
   - Add validation

## 📚 References

- [Symfony Documentation](https://symfony.com/doc/current/index.html)
- [Doctrine ORM](https://www.doctrine-project.org/projects/orm.html)
- [AWS Secrets Manager](https://docs.aws.amazon.com/secretsmanager/)
- [Docker Documentation](https://docs.docker.com/)

## 📄 License

MIT License

---

**Status**: Phase 1 & 2 Complete ✅  
**Next**: Implement User API (Phase 3)
