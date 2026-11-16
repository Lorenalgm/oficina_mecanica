<?php

namespace Infrastructure\Messaging;

enum Decisao
{
    case Confirmar;
    case Retentar;
    case Rejeitar;
}
