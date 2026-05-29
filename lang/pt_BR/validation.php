<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Linhas de Idioma para Validação
    |--------------------------------------------------------------------------
    |
    | As linhas a seguir contêm as mensagens padrão de erro usadas pela classe
    | de validação. Algumas regras possuem múltiplas versões, como as regras
    | de tamanho. Sinta-se livre para ajustar cada mensagem aqui.
    |
    */

    'accepted'             => 'O campo :attribute deve ser aceito.',
    'accepted_if'          => 'O campo :attribute deve ser aceito quando :other for :value.',
    'active_url'           => 'O campo :attribute não contém uma URL válida.',
    'after'                => 'O campo :attribute deve conter uma data posterior a :date.',
    'after_or_equal'       => 'O campo :attribute deve conter uma data superior ou igual a :date.',
    'alpha'                => 'O campo :attribute deve conter apenas letras.',
    'alpha_dash'           => 'O campo :attribute deve conter apenas letras, números, hífens e sublinhados.',
    'alpha_num'            => 'O campo :attribute deve conter apenas letras e números.',
    'array'                => 'O campo :attribute deve conter uma matriz.',
    'ascii'                => 'O campo :attribute deve conter apenas caracteres alfanuméricos e símbolos de um byte.',
    'before'               => 'O campo :attribute deve conter uma data anterior a :date.',
    'before_or_equal'      => 'O campo :attribute deve conter uma data anterior ou igual a :date.',
    'between'              => [
        'array'   => 'O campo :attribute deve conter entre :min e :max itens.',
        'file'    => 'O campo :attribute deve ter entre :min e :max kilobytes.',
        'numeric' => 'O campo :attribute deve estar entre :min e :max.',
        'string'  => 'O campo :attribute deve conter entre :min e :max caracteres.',
    ],
    'boolean'              => 'O campo :attribute deve conter o valor verdadeiro ou falso.',
    'can'                  => 'O campo :attribute contém um valor não autorizado.',
    'confirmed'            => 'O campo de confirmação de :attribute não confere.',
    'contains'            => 'O campo :attribute não contém um valor obrigatório.',
    'current_password'     => 'A senha está incorreta.',
    'date'                 => 'O campo :attribute não contém uma data válida.',
    'date_equals'          => 'O campo :attribute deve conter uma data igual a :date.',
    'date_format'          => 'O campo :attribute não corresponde ao formato :format.',
    'decimal'              => 'O campo :attribute deve ter :decimal casas decimais.',
    'declined'             => 'O campo :attribute deve ser recusado.',
    'declined_if'          => 'O campo :attribute deve ser recusado quando :other for :value.',
    'different'            => 'Os campos :attribute e :other devem ser diferentes.',
    'digits'               => 'O campo :attribute deve conter :digits dígitos.',
    'digits_between'       => 'O campo :attribute deve conter entre :min e :max dígitos.',
    'dimensions'           => 'O campo :attribute contém dimensões de imagem inválidas.',
    'distinct'             => 'O campo :attribute contém um valor duplicado.',
    'doesnt_end_with'      => 'O campo :attribute não pode terminar com um dos seguintes: :values.',
    'doesnt_start_with'    => 'O campo :attribute não pode começar com um dos seguintes: :values.',
    'email'                => 'O campo :attribute deve conter um endereço de e-mail válido.',
    'ends_with'            => 'O campo :attribute deve terminar com um dos seguintes: :values.',
    'enum'                 => 'O valor selecionado para :attribute é inválido.',
    'exists'               => 'O valor selecionado para :attribute é inválido.',
    'extensions'           => 'O campo :attribute deve ter uma das seguintes extensões: :values.',
    'file'                 => 'O campo :attribute deve conter um arquivo.',
    'filled'               => 'O campo :attribute é obrigatório.',
    'gt'                   => [
        'array'   => 'O campo :attribute deve conter mais que :value itens.',
        'file'    => 'O campo :attribute deve ser maior que :value kilobytes.',
        'numeric' => 'O campo :attribute deve ser maior que :value.',
        'string'  => 'O campo :attribute deve ser maior que :value caracteres.',
    ],
    'gte'                  => [
        'array'   => 'O campo :attribute deve conter :value itens ou mais.',
        'file'    => 'O campo :attribute deve ser maior ou igual a :value kilobytes.',
        'numeric' => 'O campo :attribute deve ser maior ou igual a :value.',
        'string'  => 'O campo :attribute deve ser maior ou igual a :value caracteres.',
    ],
    'hex_color'            => 'O campo :attribute deve conter uma cor hexadecimal válida.',
    'image'                => 'O campo :attribute deve conter uma imagem.',
    'in'                   => 'O valor selecionado para :attribute é inválido.',
    'in_array'             => 'O campo :attribute não existe em :other.',
    'integer'              => 'O campo :attribute deve conter um número inteiro.',
    'ip'                   => 'O campo :attribute deve conter um IP válido.',
    'ipv4'                 => 'O campo :attribute deve conter um IPv4 válido.',
    'ipv6'                 => 'O campo :attribute deve conter um IPv6 válido.',
    'json'                 => 'O campo :attribute deve conter uma string JSON válida.',
    'list'                 => 'O campo :attribute deve conter uma lista.',
    'lowercase'            => 'O campo :attribute deve conter apenas letras minúsculas.',
    'lt'                   => [
        'array'   => 'O campo :attribute deve conter menos que :value itens.',
        'file'    => 'O campo :attribute deve ser menor que :value kilobytes.',
        'numeric' => 'O campo :attribute deve ser menor que :value.',
        'string'  => 'O campo :attribute deve ser menor que :value caracteres.',
    ],
    'lte'                  => [
        'array'   => 'O campo :attribute deve conter no máximo :value itens.',
        'file'    => 'O campo :attribute deve ser menor ou igual a :value kilobytes.',
        'numeric' => 'O campo :attribute deve ser menor ou igual a :value.',
        'string'  => 'O campo :attribute deve ser menor ou igual a :value caracteres.',
    ],
    'mac_address'          => 'O campo :attribute deve conter um endereço MAC válido.',
    'max'                  => [
        'array'   => 'O campo :attribute deve conter no máximo :max itens.',
        'file'    => 'O campo :attribute não pode ser superior a :max kilobytes.',
        'numeric' => 'O campo :attribute não pode ser superior a :max.',
        'string'  => 'O campo :attribute não pode ser superior a :max caracteres.',
    ],
    'max_digits'           => 'O campo :attribute não pode ter mais que :max dígitos.',
    'mimes'                => 'O campo :attribute deve conter um arquivo do tipo: :values.',
    'mimetypes'            => 'O campo :attribute deve conter um arquivo do tipo: :values.',
    'min'                  => [
        'array'   => 'O campo :attribute deve conter no mínimo :min itens.',
        'file'    => 'O campo :attribute deve ter no mínimo :min kilobytes.',
        'numeric' => 'O campo :attribute deve ser no mínimo :min.',
        'string'  => 'O campo :attribute deve conter no mínimo :min caracteres.',
    ],
    'min_digits'           => 'O campo :attribute deve ter no mínimo :min dígitos.',
    'missing'              => 'O campo :attribute deve estar ausente.',
    'missing_if'           => 'O campo :attribute deve estar ausente quando :other for :value.',
    'missing_unless'       => 'O campo :attribute deve estar ausente a menos que :other seja :value.',
    'missing_with'         => 'O campo :attribute deve estar ausente quando :values estiver presente.',
    'missing_with_all'     => 'O campo :attribute deve estar ausente quando :values estiverem presentes.',
    'multiple_of'          => 'O campo :attribute deve ser um múltiplo de :value.',
    'not_in'               => 'O valor selecionado para :attribute é inválido.',
    'not_regex'            => 'O formato do campo :attribute é inválido.',
    'numeric'              => 'O campo :attribute deve conter um número.',
    'password'             => [
        'letters'       => 'O campo :attribute deve conter pelo menos uma letra.',
        'mixed'         => 'O campo :attribute deve conter pelo menos uma letra maiúscula e uma minúscula.',
        'numbers'       => 'O campo :attribute deve conter pelo menos um número.',
        'symbols'       => 'O campo :attribute deve conter pelo menos um símbolo.',
        'uncompromised' => 'A senha informada apareceu em um vazamento de dados. Escolha uma senha diferente.',
    ],
    'present'              => 'O campo :attribute deve estar presente.',
    'present_if'           => 'O campo :attribute deve estar presente quando :other for :value.',
    'present_unless'       => 'O campo :attribute deve estar presente a menos que :other seja :value.',
    'present_with'         => 'O campo :attribute deve estar presente quando :values estiver presente.',
    'present_with_all'     => 'O campo :attribute deve estar presente quando :values estiverem presentes.',
    'prohibited'           => 'O campo :attribute é proibido.',
    'prohibited_if'        => 'O campo :attribute é proibido quando :other for :value.',
    'prohibited_unless'    => 'O campo :attribute é proibido a menos que :other seja :values.',
    'prohibits'            => 'O campo :attribute proíbe que :other esteja presente.',
    'regex'                => 'O formato do campo :attribute é inválido.',
    'required'             => 'O campo :attribute é obrigatório.',
    'required_array_keys'  => 'O campo :attribute deve conter entradas para: :values.',
    'required_if'          => 'O campo :attribute é obrigatório quando :other for :value.',
    'required_if_accepted' => 'O campo :attribute é obrigatório quando :other for aceito.',
    'required_if_declined' => 'O campo :attribute é obrigatório quando :other for recusado.',
    'required_unless'      => 'O campo :attribute é obrigatório a menos que :other esteja em :values.',
    'required_with'        => 'O campo :attribute é obrigatório quando :values estiver presente.',
    'required_with_all'    => 'O campo :attribute é obrigatório quando :values estiverem presentes.',
    'required_without'     => 'O campo :attribute é obrigatório quando :values não estiver presente.',
    'required_without_all' => 'O campo :attribute é obrigatório quando nenhum de :values estiver presente.',
    'same'                 => 'Os campos :attribute e :other devem ser iguais.',
    'size'                 => [
        'array'   => 'O campo :attribute deve conter :size itens.',
        'file'    => 'O campo :attribute deve ter :size kilobytes.',
        'numeric' => 'O campo :attribute deve ser :size.',
        'string'  => 'O campo :attribute deve conter :size caracteres.',
    ],
    'starts_with'          => 'O campo :attribute deve começar com um dos seguintes: :values.',
    'string'               => 'O campo :attribute deve conter texto.',
    'timezone'             => 'O campo :attribute deve conter um fuso horário válido.',
    'unique'               => 'O valor informado para o campo :attribute já está em uso.',
    'uploaded'             => 'Falha no upload do arquivo :attribute.',
    'uppercase'            => 'O campo :attribute deve conter apenas letras maiúsculas.',
    'url'                  => 'O campo :attribute deve conter uma URL válida.',
    'ulid'                 => 'O campo :attribute deve conter um ULID válido.',
    'uuid'                 => 'O campo :attribute deve conter um UUID válido.',

    /*
    |--------------------------------------------------------------------------
    | Mensagens de Validação Personalizadas
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nomes de Atributos Personalizados
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'name'                  => 'nome',
        'nome'                  => 'nome',
        'email'                 => 'e-mail',
        'password'              => 'senha',
        'password_confirmation' => 'confirmação de senha',
        'senha'                 => 'senha',
        'cpf'                   => 'CPF',
        'cnpj'                  => 'CNPJ',
        'cpf_cnpj'              => 'CPF/CNPJ',
        'telefone'              => 'telefone',
        'perfil'               => 'perfil',
        'descricao'             => 'descrição',
        'razao_social'          => 'razão social',
        'nome_fantasia'         => 'nome fantasia',
        'nome_razao_social'     => 'nome / razão social',
        'comissao_percentual'   => 'percentual de comissão',
        'status'                => 'status',
        'preco_venda'           => 'preço de venda',
        'preco_custo'           => 'preço de custo',
        'ncm'                   => 'NCM',
        'cfop'                  => 'CFOP',
    ],

];
