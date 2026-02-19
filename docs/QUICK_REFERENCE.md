# Quick Reference Guide

## 🚀 Quick Start Commands

```bash
# Initialize project (first time)
./bin/init.sh

# Start containers
docker-compose up -d

# Stop containers
docker-compose down

# View logs
docker-compose logs -f php

# Access PHP container
docker exec -it api-restful-php bash

# Install dependencies
docker exec -it api-restful-php composer install

# Run health check
docker exec -it api-restful-php php bin/console app:test-connections
```

## 📦 Service Access

| Service | URL | Credentials |
|---------|-----|-------------|
| Application | http://localhost:8080 | - |
| MySQL | localhost:3306 | root / mysql_secret |
| SQL Server | localhost:1433 | sa / SqlServer2024! |
| Redis | localhost:6379 | - |

## 🗄️ Database Clients

### MySQL
```bash
# CLI
docker exec -it api-restful-mysql mysql -uroot -pmysql_secret users_db

# Query
docker exec -it api-restful-mysql mysql -uroot -pmysql_secret -e "SHOW DATABASES;"
```

### SQL Server
```bash
# CLI
docker exec -it api-restful-sqlserver /opt/mssql-tools/bin/sqlcmd -S localhost -U sa -P "SqlServer2024!"

# Query
docker exec -it api-restful-sqlserver /opt/mssql-tools/bin/sqlcmd -S localhost -U sa -P "SqlServer2024!" -Q "SELECT name FROM sys.databases;"
```

### Redis
```bash
# CLI
docker exec -it api-restful-redis redis-cli

# Common commands
KEYS *                    # List all keys
GET aws_secret:*          # Get cached secret
FLUSHDB                   # Clear cache
INFO                      # Server info
```

## 🔧 Symfony Commands

```bash
# List all commands
php bin/console list

# Test connections
php bin/console app:test-connections

# Clear cache
php bin/console cache:clear

# List routes (when implemented)
php bin/console debug:router

# Check services
php bin/console debug:container connection_manager
```

## 🧪 Testing Connection Manager

### From PHP Container
```bash
docker exec -it api-restful-php bash
php bin/console app:test-connections
```

### Programmatically
```php
// Get the service
$connectionManager = $container->get('connection_manager');

// MySQL connection
$mysqlConn = $connectionManager->getConnection('api-restful/mysql_users');
$result = $mysqlConn->getConnection()->executeQuery('SELECT 1')->fetchOne();

// SQL Server connection
$sqlServerConn = $connectionManager->getConnection('api-restful/sqlserver_books');
$result = $sqlServerConn->getConnection()->executeQuery('SELECT 1')->fetchOne();
```

## 🔐 AWS Commands

### List Secrets
```bash
aws secretsmanager list-secrets --region us-east-1
```

### Get Secret Value
```bash
aws secretsmanager get-secret-value \
    --secret-id api-restful/mysql_users \
    --region us-east-1
```

### Update Secret
```bash
aws secretsmanager update-secret \
    --secret-id api-restful/mysql_users \
    --secret-string '{"driver":"mysql","host":"mysql","port":3306}' \
    --region us-east-1
```

### Delete Secret
```bash
aws secretsmanager delete-secret \
    --secret-id api-restful/mysql_users \
    --force-delete-without-recovery \
    --region us-east-1
```

## 📝 Environment Variables

### Required Variables
```bash
# App
APP_ENV=dev
APP_SECRET=your_secret

# AWS (if using Secrets Manager)
USE_AWS_SECRETS=true
AWS_REGION=us-east-1
AWS_ACCESS_KEY_ID=your_key
AWS_SECRET_ACCESS_KEY=your_secret

# Local Fallback (if not using AWS)
USE_AWS_SECRETS=false
MYSQL_HOST=mysql
MYSQL_PASSWORD=mysql_secret
SQLSERVER_HOST=sqlserver
SQLSERVER_PASSWORD=SqlServer2024!
```

## 🐛 Troubleshooting

### Container won't start
```bash
docker-compose down -v
docker system prune -a
docker-compose up -d --build
```

### SQL Server connection issues
```bash
# Check health
docker-compose ps

# View logs
docker-compose logs sqlserver

# Verify connection
docker exec -it api-restful-sqlserver /opt/mssql-tools/bin/sqlcmd -S localhost -U sa -P "SqlServer2024!" -Q "SELECT @@VERSION"
```

### Composer issues
```bash
docker exec -it api-restful-php composer diagnose
docker exec -it api-restful-php composer clear-cache
docker exec -it api-restful-php composer update
```

### Redis cache issues
```bash
# Clear all cache
docker exec -it api-restful-redis redis-cli FLUSHALL

# Restart Redis
docker-compose restart redis
```

### Permission issues
```bash
chmod +x bin/console
chmod +x bin/init.sh
chmod +x bin/health-check
```

## 📊 Useful Queries

### MySQL - Check tables
```sql
USE users_db;
SHOW TABLES;
DESCRIBE users;
SELECT * FROM users LIMIT 10;
```

### SQL Server - Check tables
```sql
USE books_db;
SELECT * FROM INFORMATION_SCHEMA.TABLES;
SELECT * FROM books;
```

### Redis - Check cache
```bash
# List all AWS secret keys
KEYS aws_secret:*

# Get specific secret
GET aws_secret:api-restful/mysql_users

# Check TTL
TTL aws_secret:api-restful/mysql_users
```

## 🎯 Code Examples

### Get Connection (Service)
```php
use App\Shared\Infrastructure\Database\ConnectionManager;

class MyService
{
    public function __construct(
        private ConnectionManager $connectionManager
    ) {}

    public function myMethod(): void
    {
        $conn = $this->connectionManager
            ->getConnection('api-restful/mysql_users')
            ->getConnection();
    }
}
```

### Entity Manager (Write)
```php
use Doctrine\ORM\EntityManagerInterface;

class CreateUserService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function execute(array $data): User
    {
        $user = new User($data['email'], $data['name']);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        return $user;
    }
}
```

### DBAL (Read)
```php
use App\Shared\Infrastructure\Database\ConnectionManager;

class UserQueryService
{
    public function __construct(
        private ConnectionManager $connectionManager
    ) {}

    public function findByEmail(string $email): ?array
    {
        $conn = $this->connectionManager
            ->getConnection('api-restful/mysql_users')
            ->getConnection();

        return $conn->executeQuery(
            'SELECT * FROM users WHERE email = ?',
            [$email]
        )->fetchAssociative();
    }
}
```

## 🔄 Workflow

### Development Workflow
```bash
1. Start containers: docker-compose up -d
2. Make changes in code
3. Test: docker exec -it api-restful-php php bin/console app:test-connections
4. Access app: http://localhost:8080
5. Check logs: docker-compose logs -f php
```

### Production Deployment
```bash
1. Set USE_AWS_SECRETS=true
2. Configure AWS credentials
3. Create secrets in AWS
4. Build: docker-compose -f docker-compose.prod.yml build
5. Deploy containers
```

## 📚 Documentation Links

- [Main README](../README.md)
- [AWS Setup Guide](AWS_SECRETS_SETUP.md)
- [Architecture Overview](ARCHITECTURE.md)
- [Phase 1+2 Complete](FASE_1_2_COMPLETADA.md)

## 🎓 Next Steps

After Phase 1+2:
1. ✅ Verify all connections work
2. 📝 Implement User API (Phase 3)
3. 📝 Implement Book API (Phase 4)
4. 📝 Add DynamoDB/Redis examples (Phase 5)
