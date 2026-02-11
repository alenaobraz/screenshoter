#!/usr/bin/env bash

compose_services=(
    "percona"
    "edge"
    "php-fpm"
    "rabbitmq"
    "worker"
    "screenshoter"
)

execute_docker_command() {
    if [[ "$1" == "up" ]]; then
        docker compose up -d $2 
        if [[ $? != 0 ]]; then
            exit 1
        fi
    fi

    if [[ "$1" == "down" ]]; then
        docker compose down $2 --remove-orphans
        if [[ $? != 0 ]]; then
            exit 1
        fi
    fi

    if [[ "$1" == "restart" ]]; then
        docker compose restart $2
        if [[ $? != 0 ]]; then
            exit 1
        fi
    fi
}

if [[ -z $2 ]]; then
    PS3='Выберите сервис: '
    options=("Все" "${compose_services[@]}")
    select opt in "${options[@]}"; do
        case "$opt" in
        "Все")
            selected="all"
            break
            ;;
        *)
            selected=${opt}
            break
            ;;
        esac
    done
else
    selected=$2
fi

if [[ ${selected} == "all" ]]; then
    for compose_file in "${compose_services[@]}"; do
        execute_docker_command $1 ${compose_file}
    done
else
    execute_docker_command $1 ${selected}
fi
