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