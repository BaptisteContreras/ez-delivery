#!/bin/bash

usermod -u "$USER" ez-delivery

runuser -u ez-delivery -- "${@:1}"
