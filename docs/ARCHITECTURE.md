# Architecture Overview

## System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                         REST API CLIENTS                             │
└────────────────────────────────┬────────────────────────────────────┘
                                 │
                    ┌────────────▼───────────┐
                    │   Nginx (Port 8080)     │
                    └────────────┬───────────┘
                                 │
                    ┌────────────▼───────────┐
                    │   Symfony 7.1 App       │
                    │   (PHP 8.3-FPM)         │
                    └────────────┬───────────┘
                                 │
        ┌────────────────────────┼────────────────────────┐
        │                        │                        │
        ▼                        ▼                        ▼
┌───────────────┐    ┌──────────────────┐    ┌──────────────────┐
│  User Module  │    │  Book Module     │    │ Shared Kernel    │
│  (Bounded     │    │  (Bounded        │    │                  │
│   Context)    │    │   Context)       │    │ - Connection     │
│               │    │                  │    │   Factory        │
│ - Domain      │    │ - Domain         │    │ - Connection     │
│ - Application │    │ - Application    │    │   Manager        │
│ - Infra       │    │ - Infra          │    │ - AWS Secrets    │
└───────┬───────┘    └────────┬─────────┘    └────────┬─────────┘
        │                     │                       │
        └─────────────────────┼───────────────────────┘
                              │
                 ┌────────────▼───────────┐
                 │  Connection Manager     │
                 │  (Singleton Pattern)    │
                 └────────────┬───────────┘
                              │
            ┌─────────────────┼─────────────────┐
            │                 │                 │
            ▼                 ▼                 ▼
    ┌───────────────┐ ┌──────────────┐ ┌─────────────┐
    │ Secrets Cache │ │  Connection  │ │  Local      │
    │  (Redis)      │ │  Factory     │ │  Config     │
    └───────┬───────┘ └──────┬───────┘ └─────┬───────┘
            │                │                │
            ▼                │                │
    ┌───────────────┐        │                │
    │ AWS Secrets   │        │                │
    │  Manager      │        │                │
    └───────────────┘        │                │
                             │                │
            ┌────────────────┴────────────────┴────────────┐
            │                                              │
            ▼              ▼              ▼               ▼
    ┌────────────┐ ┌────────────┐ ┌──────────┐ ┌─────────────┐
    │   MySQL    │ │ SQL Server │ │  Redis   │ │  DynamoDB   │
    │  Connector │ │ Connector  │ │Connector │ │  Connector  │
    └─────┬──────┘ └─────┬──────┘ └────┬─────┘ └──────┬──────┘
          │              │             │              │
          ▼              ▼             ▼              ▼
    ┌────────────┐ ┌────────────┐ ┌──────────┐ ┌─────────────┐
    │   MySQL    │ │ SQL Server │ │  Redis   │ │  DynamoDB   │
    │   8.0      │ │   2022     │ │    7     │ │    (AWS)    │
    │ (Port 3306)│ │(Port 1433) │ │(Port 6379)│ │             │
    │            │ │            │ │          │ │             │
    │ users_db   │ │ books_db   │ │ Cache    │ │  Future     │
    └────────────┘ └────────────┘ └──────────┘ └─────────────┘
```

## Request Flow

### Example: Create User Request

```
1. Client Request
   POST /api/users
   ↓
2. Nginx receives request
   ↓
3. Routes to PHP-FPM (Symfony)
   ↓
4. UserController receives request
   ↓
5. Calls CreateUserUseCase (Application Layer)
   ↓
6. Use Case validates data
   ↓
7. Creates User entity (Domain Layer)
   ↓
8. Calls UserRepository->save() (Infrastructure Layer)
   ↓
9. Repository requests connection from ConnectionManager
   ↓
10. ConnectionManager checks if connection exists
    ↓
11a. If cached → return connection
11b. If not cached:
     ↓
     12. Check SecretsCache (Redis)
         ↓
     13a. If in cache → use cached credentials
     13b. If not in cache:
          ↓
          14. Fetch from AWS Secrets Manager
          ↓
          15. Cache in Redis (1 hour TTL)
     ↓
     16. ConnectionFactory creates MySQLConnector
     ↓
     17. MySQLConnector establishes Doctrine DBAL connection
     ↓
18. EntityManager persists User entity
    ↓
19. Doctrine executes INSERT query to MySQL
    ↓
20. Return success response to client
```

## Connection Manager Flow

```
┌─────────────────────────────────────────────────────────────┐
│              Service needs database connection              │
└───────────────────────────┬─────────────────────────────────┘
                            │
                            ▼
              ┌─────────────────────────┐
              │  connectionManager      │
              │  ->getConnection(name)  │
              └────────────┬────────────┘
                           │
                 ┌─────────▼──────────┐
                 │  Connection in     │
                 │  cache?            │
                 └─┬─────────────┬────┘
              YES  │             │  NO
                   ▼             ▼
           ┌──────────────┐  ┌───────────────┐
           │ Return cached│  │ Get config    │
           │ connection   │  │ from AWS or   │
           └──────────────┘  │ local         │
                             └───────┬───────┘
                                     │
                        ┌────────────▼────────────┐
                        │ ConnectionFactory       │
                        │ ->create(driver, config)│
                        └────────────┬────────────┘
                                     │
                    ┌────────────────┼────────────────┐
                    │                │                │
                    ▼                ▼                ▼
            ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
            │MySQL         │ │SQLServer     │ │Redis         │
            │Connector     │ │Connector     │ │Connector     │
            └──────┬───────┘ └──────┬───────┘ └──────┬───────┘
                   │                │                │
                   └────────────────┼────────────────┘
                                    │
                        ┌───────────▼──────────┐
                        │ Cache connection     │
                        │ Return to service    │
                        └──────────────────────┘
```

## AWS Secrets Manager Integration

```
┌─────────────────────────────────────────────────────────────┐
│              First time fetching credentials                │
└───────────────────────────┬─────────────────────────────────┘
                            │
                            ▼
              ┌─────────────────────────┐
              │  SecretsManagerService  │
              │  ->getSecret(name)      │
              └────────────┬────────────┘
                           │
                 ┌─────────▼──────────┐
                 │  Check Redis cache │
                 └─┬─────────────┬────┘
              YES  │             │  NO
                   ▼             ▼
           ┌──────────────┐  ┌──────────────────┐
           │ Return from  │  │ AWS Secrets      │
           │ cache        │  │ Manager API call │
           └──────────────┘  └────────┬─────────┘
                                      │
                            ┌─────────▼─────────┐
                            │ Parse JSON secret │
                            └─────────┬─────────┘
                                      │
                            ┌─────────▼─────────┐
                            │ Cache in Redis    │
                            │ (TTL: 1 hour)     │
                            └─────────┬─────────┘
                                      │
                            ┌─────────▼─────────┐
                            │ Return credentials│
                            └───────────────────┘

Cost Savings:
- Without cache: $0.05 per 10,000 calls
- With cache (1h TTL): ~99% reduction in API calls
```

## Multi-Database Entity Manager Strategy

```
┌─────────────────────────────────────────────────────────────┐
│                    Doctrine Configuration                    │
└───────────────────────────┬─────────────────────────────────┘
                            │
            ┌───────────────┴───────────────┐
            │                               │
            ▼                               ▼
┌───────────────────────┐       ┌───────────────────────┐
│  Entity Manager       │       │  Entity Manager       │
│  "mysql_users"        │       │  "sqlserver_books"    │
│                       │       │                       │
│  Connection:          │       │  Connection:          │
│  mysql_users          │       │  sqlserver_books      │
│                       │       │                       │
│  Manages:             │       │  Manages:             │
│  - User entity        │       │  - Book entity        │
│                       │       │                       │
│  Mapping Path:        │       │  Mapping Path:        │
│  src/User/Domain/     │       │  src/Book/Domain/     │
│  Entity/              │       │  Entity/              │
└───────────┬───────────┘       └───────────┬───────────┘
            │                               │
            ▼                               ▼
    ┌───────────────┐               ┌───────────────┐
    │   MySQL DB    │               │ SQL Server DB │
    │   users_db    │               │   books_db    │
    └───────────────┘               └───────────────┘

Usage Pattern:
- WRITES: Use Entity Manager (ORM)
  └─> $entityManager->persist($entity)
      $entityManager->flush()

- COMPLEX READS: Use DBAL (Raw SQL)
  └─> $connection = $connectionManager->getConnection()
      $connection->executeQuery($sql)
```

## SOLID Principles Applied

```
Single Responsibility:
├─ SecretsManagerService → Only AWS communication
├─ SecretsCache → Only caching logic
├─ ConnectionFactory → Only connection creation
├─ ConnectionManager → Only connection lifecycle
└─ Each Connector → Only specific DB connection

Open/Closed:
└─ Add new database? → Create new Connector
   (No modification to existing code)

Liskov Substitution:
└─ All Connectors implement DatabaseConnectionInterface
   (Interchangeable)

Interface Segregation:
└─ DatabaseConnectionInterface → Only essential methods
   (isConnected, getConnection, disconnect, etc.)

Dependency Inversion:
├─ Services depend on DatabaseConnectionInterface
│  (not concrete implementations)
└─ Configuration injected via DI Container
```

## File Organization by Responsibility

```
Shared Kernel (Cross-cutting concerns):
├─ Domain/
│  └─ Contracts/ → Interfaces only
└─ Infrastructure/
   ├─ AWS/ → AWS-specific implementations
   ├─ Database/ → Database abstraction layer
   └─ Console/ → CLI commands

Bounded Contexts (Business logic):
├─ User/ (MySQL)
│  ├─ Domain/ → Entities, Value Objects, Repository Interfaces
│  ├─ Application/ → Use Cases, DTOs
│  └─ Infrastructure/ → Repository Implementations, Controllers
└─ Book/ (SQL Server)
   ├─ Domain/
   ├─ Application/
   └─ Infrastructure/
```

This architecture provides:
- ✅ Centralized connection management
- ✅ Easy to add new databases
- ✅ Secure credential management
- ✅ Cost-optimized (caching)
- ✅ Testable (interfaces)
- ✅ SOLID principles
- ✅ Clean Architecture
- ✅ Multi-tenant ready
