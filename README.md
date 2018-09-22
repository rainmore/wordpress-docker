# Wordpress Docker

Access URL http://localhost:8080

## Docker

Start docker

```
docker-compose up -d
```

Stop docker

```
docker-compose stop
```

Check the log

```
docker-compose logs -f
```

## Config

DB

Create an environment file `./config/mysql.env` with following content

```
MYSQL_ROOT_PASSWORD=password
```

Wordpress

Create an environment file `./config/wordpress.env` with following content

```
WORDPRESS_DB_PASSWORD=password
WORDPRESS_TABLE_PREFIX=wp_
```

docker run -it --link kb_db_1:mysql --rm mariadb sh -c 'exec mysql -h"$MYSQL_PORT_3306_TCP_ADDR" -P"$MYSQL_PORT_3306_TCP_PORT" -uroot -p"$MYSQL_ENV_MYSQL_ROOT_PASSWORD"'

## Development

Shell access

```
docker exec -it kb_db_1 bash
```

check db log

```
docker logs -f kb_db_1
```

db databases

access db

```
docker exec -it kb_db_1 sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD"'
``

dump 

```
docker exec kb_db_1 sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" wordpress' > ./wordpress_bak.sql
```

rebuild 

```
docker exec kb_db_1 sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "drop database wordpress; create database wordpress;"'
```

load backup 

```
docker exec -i kb_db_1 mysql -uroot -p{password} wordpress < ./wordpress_bak.sql
```