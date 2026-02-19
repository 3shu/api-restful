# AWS Secrets Manager Setup Guide

This guide will help you configure AWS Secrets Manager for the project.

## Prerequisites

- AWS Account (free tier is sufficient)
- AWS CLI installed and configured
- IAM user with Secrets Manager permissions

## Step 1: Install AWS CLI

### macOS
```bash
brew install awscli
```

### Verify Installation
```bash
aws --version
```

## Step 2: Configure AWS Credentials

### Option A: Using AWS CLI Configure
```bash
aws configure
```

You'll be prompted for:
- AWS Access Key ID
- AWS Secret Access Key
- Default region (e.g., us-east-1)
- Default output format (json)

### Option B: Manual Configuration

Create `~/.aws/credentials`:
```ini
[default]
aws_access_key_id = YOUR_ACCESS_KEY
aws_secret_access_key = YOUR_SECRET_KEY
```

Create `~/.aws/config`:
```ini
[default]
region = us-east-1
output = json
```

## Step 3: Create IAM User with Secrets Manager Permissions

### Create IAM Policy

Create a file `secretsmanager-policy.json`:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "secretsmanager:GetSecretValue",
        "secretsmanager:DescribeSecret",
        "secretsmanager:ListSecrets",
        "secretsmanager:CreateSecret",
        "secretsmanager:UpdateSecret"
      ],
      "Resource": "arn:aws:secretsmanager:*:*:secret:api-restful/*"
    }
  ]
}
```

### Create the Policy
```bash
aws iam create-policy \
    --policy-name ApiRestfulSecretsManagerPolicy \
    --policy-document file://secretsmanager-policy.json
```

### Attach to User
```bash
aws iam attach-user-policy \
    --user-name YOUR_IAM_USERNAME \
    --policy-arn arn:aws:iam::YOUR_ACCOUNT_ID:policy/ApiRestfulSecretsManagerPolicy
```

## Step 4: Create Secrets

### MySQL Users Secret

```bash
aws secretsmanager create-secret \
    --name api-restful/mysql_users \
    --description "MySQL credentials for Users microservice" \
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

### SQL Server Books Secret

```bash
aws secretsmanager create-secret \
    --name api-restful/sqlserver_books \
    --description "SQL Server credentials for Books microservice" \
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

## Step 5: Verify Secrets

### List All Secrets
```bash
aws secretsmanager list-secrets --region us-east-1
```

### Get Specific Secret Value
```bash
aws secretsmanager get-secret-value \
    --secret-id api-restful/mysql_users \
    --region us-east-1 \
    --query SecretString \
    --output text | jq .
```

```bash
aws secretsmanager get-secret-value \
    --secret-id api-restful/sqlserver_books \
    --region us-east-1 \
    --query SecretString \
    --output text | jq .
```

## Step 6: Update Project Configuration

Edit `.env.local`:

```bash
# Enable AWS Secrets Manager
USE_AWS_SECRETS=true

# AWS Configuration
AWS_REGION=us-east-1
AWS_ACCESS_KEY_ID=your_actual_access_key
AWS_SECRET_ACCESS_KEY=your_actual_secret_key
```

**⚠️ IMPORTANT**: Never commit `.env.local` to git!

## Step 7: Test Integration

### Start Docker Containers
```bash
docker-compose up -d
```

### Run Health Check
```bash
docker exec -it api-restful-php php bin/console app:test-connections
```

Expected output:
```
✅ MySQL - Users Database: CONNECTED
✅ SQL Server - Books Database: CONNECTED
🎉 All connections are healthy!
```

## Step 8: Update Secrets (When Needed)

### Update MySQL Secret
```bash
aws secretsmanager update-secret \
    --secret-id api-restful/mysql_users \
    --secret-string '{
        "driver": "mysql",
        "host": "127.0.0.1",
        "port": 3306,
        "database": "users_db",
        "user": "new_user",
        "password": "new_password",
        "charset": "utf8mb4"
    }' \
    --region us-east-1
```

### Clear Cache (Force Refresh)
```bash
docker exec -it api-restful-php php bin/console cache:clear
# Or restart containers
docker-compose restart
```

## Troubleshooting

### Error: "Access Denied"

**Solution**: Check IAM permissions
```bash
aws iam list-attached-user-policies --user-name YOUR_USERNAME
```

### Error: "Secret not found"

**Solution**: Verify secret name
```bash
aws secretsmanager list-secrets --region us-east-1
```

### Error: "Invalid credentials"

**Solution**: Verify AWS credentials
```bash
aws sts get-caller-identity
```

### Cache Issues

**Solution**: Clear Redis cache
```bash
docker exec -it api-restful-redis redis-cli FLUSHDB
```

## Cost Optimization

### Free Tier Limits
- 30-day free trial for Secrets Manager
- $0.40 per secret per month (after trial)
- $0.05 per 10,000 API calls

### Tips to Minimize Costs
1. Use Redis caching (already implemented - 1 hour TTL)
2. Limit secret rotations
3. Delete unused secrets
4. Use local development mode when possible

## Informe técnico (resumen ejecutivo)

Este repositorio implementa un enfoque de **microservicios por contexto** (`User` y `Book`) con un **Shared Kernel** que centraliza:

- Gestión de conexiones a base de datos (`ConnectionManager` + `ConnectionFactory`)
- Acceso seguro a credenciales (`SecretsManagerService`)
- Caché de secretos en Redis (`SecretsCache`, TTL 1 hora)

### ¿Por qué mantener esta arquitectura?

1. **Escalabilidad operativa**: cada microservicio puede evolucionar y desplegarse con independencia.
2. **Menor acoplamiento**: separar dominios (usuarios/libros) reduce impacto de cambios.
3. **Seguridad consistente**: las credenciales no viven en código ni en archivos versionados.
4. **Operación simple**: un único punto para resolver conexiones y secretos evita duplicación.
5. **Auditoría y gobierno**: AWS Secrets Manager permite trazabilidad de acceso y rotación.

### Estimación de costo mensual en AWS Secrets Manager

Supuestos del escenario pedido:

- 20 personas
- 7 días por semana, **30 días/mes** (supuesto fijo para el cálculo)
- 15 horas por día por persona (turnos rotativos)
- 2 secretos activos (los de este repo: MySQL + SQL Server)
- Precio de referencia: **USD 0.40/secreto/mes** y **USD 0.05 por cada 10,000 llamadas** (`GetSecretValue`)

Costo fijo de almacenamiento:

- `2 secretos x USD 0.40 = USD 0.80/mes`

Costo variable por obtención de secretos (sin cache compartida, consulta directa por intervalo):

| Intervalo de obtención | Llamadas/mes (20 personas, 2 secretos) | Costo por API calls | Costo mensual total |
|---|---:|---:|---:|
| Cada 60 min | 18,000 | USD 0.09 | **USD 0.89** |
| Cada 30 min | 36,000 | USD 0.18 | **USD 0.98** |
| Cada 15 min | 72,000 | USD 0.36 | **USD 1.16** |
| Cada 5 min | 216,000 | USD 1.08 | **USD 1.88** |

> Fórmula usada: `llamadas_mes = personas x horas_dia x (60 / intervalo_min) x dias_mes x secretos`.
> Donde `horas_dia` son horas por persona por día.
>
> Ejemplo (cada 60 min):
> `20 x 15 x (60/60) x 30 x 2 = 18,000 llamadas/mes`.
>
> Costo API calls: `(llamadas_mes / 10,000) x USD 0.05` (mostrado con 2 decimales).

Con la estrategia actual del repositorio (caché Redis con TTL de 1 hora), el costo variable baja de forma importante porque se evita consultar AWS en cada operación. En la práctica, el costo suele estar dominado por el número de secretos almacenados más que por las llamadas API.

### Delete Secrets (If Not Needed)
```bash
aws secretsmanager delete-secret \
    --secret-id api-restful/mysql_users \
    --force-delete-without-recovery \
    --region us-east-1
```

## Production Recommendations

1. **Use Secret Rotation**: Enable automatic rotation for production
2. **Separate Environments**: Create different secrets for dev/staging/prod
3. **IAM Roles**: Use IAM roles instead of access keys in EC2/ECS
4. **VPC Endpoints**: Use VPC endpoints to avoid internet traffic costs
5. **Monitoring**: Enable CloudWatch logging for secret access

## References

- [AWS Secrets Manager Documentation](https://docs.aws.amazon.com/secretsmanager/)
- [AWS CLI Secrets Manager Commands](https://docs.aws.amazon.com/cli/latest/reference/secretsmanager/)
- [Best Practices](https://docs.aws.amazon.com/secretsmanager/latest/userguide/best-practices.html)
