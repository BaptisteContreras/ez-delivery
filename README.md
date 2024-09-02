# EZ DELIVERY

Tool to create a release branch with commits from pull requests linked to a Github issue with specific labels

Requires [Castor](https://castor.jolicode.com/) and PHP to work

## Init

```
castor init
```

Then fill the config fields

## Create a delivery

```
castor create-package <project>
```

## Package application

```
vendor/bin/castor repack --app-name ez-delivery
```

## Build prod

```
docker build --target prod -f prod\Dockerfile -t ez-delivery .
```

```
docker run -v ~/.ssh:/home/ez-delivery/.ssh -v ~/ez-deliver/var/:/home/ez-delivery/.ez  -v ~/.gitconfig:/home/ez-delivery/.gitconfig -v $(pwd):/app -e USER=$UID  -it base_prod:latest ez-delivery
```