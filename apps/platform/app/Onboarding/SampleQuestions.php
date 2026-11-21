<?php

declare(strict_types=1);

namespace App\Onboarding;

use App\Enums\QuizTopic;

/**
 * Sample exam-style questions for the free quiz (mobile journey screen 6),
 * marked as samples until the licensed Texas question bank arrives with
 * course content (A5.3). Each has an explanation and its source.
 */
final class SampleQuestions
{
    /**
     * @return list<array{key: string, state_code: string|null, topic: QuizTopic, correct_option: int, source: string, content: array{en: array{question: string, options: list<string>, explanation: string}, es: array{question: string, options: list<string>, explanation: string}}}>
     */
    public static function all(): array
    {
        return [
            [
                'key' => 'agency-disclose-to-client',
                'state_code' => null,
                'topic' => QuizTopic::Agency,
                'correct_option' => 1,
                'source' => 'Licensing exam content outline · Agency: duties to clients',
                'content' => [
                    'en' => [
                        'question' => "A buyer's agent learns the seller will accept less. The buyer asks. What should the agent do?",
                        'options' => ['Keep it to themselves', 'Share it with the buyer, their client', "Tell the seller's agent first", 'Ask the broker to decide'],
                        'explanation' => "A buyer's agent owes the buyer loyalty and disclosure of facts that matter to them.",
                    ],
                    'es' => [
                        'question' => 'El agente del comprador se entera de que el vendedor aceptaría menos. El comprador pregunta. ¿Qué debe hacer el agente?',
                        'options' => ['Guardárselo', 'Decírselo al comprador, que es su cliente', 'Avisar primero al agente del vendedor', 'Pedir al bróker que decida'],
                        'explanation' => 'El agente del comprador le debe lealtad y debe informarle de los hechos que le importan.',
                    ],
                ],
            ],
            [
                'key' => 'texas-iabs',
                'state_code' => 'TX',
                'topic' => QuizTopic::StateRules,
                'correct_option' => 2,
                'source' => 'TREC Rules, 22 TAC §531.20 (Information About Brokerage Services)',
                'content' => [
                    'en' => [
                        'question' => 'When must a Texas license holder give the Information About Brokerage Services (IABS) notice?',
                        'options' => ['At closing', 'Only if the person asks for it', 'At the first substantive communication about a specific property', 'When the listing agreement is signed'],
                        'explanation' => 'Texas requires the IABS notice at the first substantive communication with a party about a specific property.',
                    ],
                    'es' => [
                        'question' => '¿Cuándo debe un titular de licencia en Texas entregar el aviso de Información sobre Servicios de Corretaje (IABS)?',
                        'options' => ['Al cierre', 'Solo si la persona lo pide', 'En la primera comunicación sustancial sobre una propiedad concreta', 'Al firmar el contrato de listado'],
                        'explanation' => 'Texas exige el aviso IABS en la primera comunicación sustancial con una parte sobre una propiedad concreta.',
                    ],
                ],
            ],
            [
                'key' => 'math-commission',
                'state_code' => null,
                'topic' => QuizTopic::Math,
                'correct_option' => 0,
                'source' => 'Licensing exam content outline · Real estate math: commissions',
                'content' => [
                    'en' => [
                        'question' => 'A house sells for $250,000 with a 6% commission. What is the total commission?',
                        'options' => ['$15,000', '$1,500', '$25,000', '$150,000'],
                        'explanation' => '$250,000 × 0.06 = $15,000.',
                    ],
                    'es' => [
                        'question' => 'Una casa se vende en $250,000 con una comisión del 6%. ¿Cuál es la comisión total?',
                        'options' => ['$15,000', '$1,500', '$25,000', '$150,000'],
                        'explanation' => '$250,000 × 0.06 = $15,000.',
                    ],
                ],
            ],
            [
                'key' => 'math-area',
                'state_code' => null,
                'topic' => QuizTopic::Math,
                'correct_option' => 3,
                'source' => 'Licensing exam content outline · Real estate math: area',
                'content' => [
                    'en' => [
                        'question' => 'A lot measures 50 feet by 120 feet. How many square feet is it?',
                        'options' => ['170', '600', '12,000', '6,000'],
                        'explanation' => 'Area is length × width: 50 × 120 = 6,000 square feet.',
                    ],
                    'es' => [
                        'question' => 'Un terreno mide 50 por 120 pies. ¿Cuántos pies cuadrados tiene?',
                        'options' => ['170', '600', '12,000', '6,000'],
                        'explanation' => 'El área es largo × ancho: 50 × 120 = 6,000 pies cuadrados.',
                    ],
                ],
            ],
            [
                'key' => 'finance-ltv',
                'state_code' => null,
                'topic' => QuizTopic::Finance,
                'correct_option' => 1,
                'source' => 'Licensing exam content outline · Financing: loan-to-value',
                'content' => [
                    'en' => [
                        'question' => 'A buyer borrows $200,000 for a home appraised at $250,000. What is the loan-to-value ratio?',
                        'options' => ['75%', '80%', '20%', '125%'],
                        'explanation' => 'Loan ÷ value: $200,000 ÷ $250,000 = 0.80, or 80%.',
                    ],
                    'es' => [
                        'question' => 'Un comprador pide un préstamo de $200,000 para una casa tasada en $250,000. ¿Cuál es la relación préstamo-valor?',
                        'options' => ['75%', '80%', '20%', '125%'],
                        'explanation' => 'Préstamo ÷ valor: $200,000 ÷ $250,000 = 0.80, es decir, 80%.',
                    ],
                ],
            ],
            [
                'key' => 'finance-note',
                'state_code' => null,
                'topic' => QuizTopic::Finance,
                'correct_option' => 2,
                'source' => 'Licensing exam content outline · Financing: notes and security instruments',
                'content' => [
                    'en' => [
                        'question' => "Which document is the borrower's personal promise to repay a mortgage loan?",
                        'options' => ['Deed of trust', 'Title policy', 'Promissory note', 'Closing disclosure'],
                        'explanation' => 'The note is the promise to repay. The deed of trust secures it with the property.',
                    ],
                    'es' => [
                        'question' => '¿Qué documento es la promesa personal del prestatario de pagar un préstamo hipotecario?',
                        'options' => ['Escritura de fideicomiso (deed of trust)', 'Póliza de título', 'Pagaré', 'Declaración de cierre'],
                        'explanation' => 'El pagaré es la promesa de pago. La escritura de fideicomiso lo garantiza con la propiedad.',
                    ],
                ],
            ],
            [
                'key' => 'texas-community-property',
                'state_code' => 'TX',
                'topic' => QuizTopic::Ownership,
                'correct_option' => 0,
                'source' => 'Texas Family Code §3.002 (community property)',
                'content' => [
                    'en' => [
                        'question' => 'In Texas, property either spouse acquires during marriage, other than separate property, is:',
                        'options' => ['Community property', 'Separate property of the spouse who paid', 'Joint tenancy by default', 'Tenancy by the entirety'],
                        'explanation' => 'Texas is a community property state: property acquired during marriage, other than separate property, belongs to both spouses.',
                    ],
                    'es' => [
                        'question' => 'En Texas, los bienes que cualquiera de los cónyuges adquiere durante el matrimonio, salvo los bienes separados, son:',
                        'options' => ['Bienes gananciales (community property)', 'Bienes separados del cónyuge que pagó', 'Copropiedad conjunta por defecto', 'Tenencia por la totalidad'],
                        'explanation' => 'Texas es un estado de bienes gananciales: lo adquirido durante el matrimonio, salvo los bienes separados, pertenece a ambos cónyuges.',
                    ],
                ],
            ],
            [
                'key' => 'ownership-fee-simple',
                'state_code' => null,
                'topic' => QuizTopic::Ownership,
                'correct_option' => 1,
                'source' => 'Licensing exam content outline · Property ownership: estates in land',
                'content' => [
                    'en' => [
                        'question' => 'Which estate gives the most complete ownership of real property?',
                        'options' => ['Life estate', 'Fee simple absolute', 'Leasehold estate', 'Estate at will'],
                        'explanation' => 'Fee simple absolute is the fullest form of ownership: it lasts indefinitely and can be sold or inherited.',
                    ],
                    'es' => [
                        'question' => '¿Qué tipo de dominio da la propiedad más completa de un inmueble?',
                        'options' => ['Usufructo vitalicio', 'Pleno dominio absoluto (fee simple absolute)', 'Arrendamiento', 'Tenencia a voluntad'],
                        'explanation' => 'El pleno dominio absoluto es la forma más completa de propiedad: no tiene plazo y se puede vender o heredar.',
                    ],
                ],
            ],
            [
                'key' => 'contracts-statute-of-frauds',
                'state_code' => 'TX',
                'topic' => QuizTopic::Contracts,
                'correct_option' => 3,
                'source' => 'Texas Business and Commerce Code §26.01 (statute of frauds)',
                'content' => [
                    'en' => [
                        'question' => 'Under the statute of frauds, a contract to sell real estate must be:',
                        'options' => ['Witnessed by two people', 'Recorded at the county', 'Notarized', 'In writing and signed to be enforceable'],
                        'explanation' => 'A contract for the sale of real estate must be in writing and signed by the party it is enforced against.',
                    ],
                    'es' => [
                        'question' => 'Según la ley contra el fraude (statute of frauds), un contrato de compraventa de bienes raíces debe:',
                        'options' => ['Tener dos testigos', 'Registrarse en el condado', 'Estar notariado', 'Constar por escrito y firmado para poder exigirse'],
                        'explanation' => 'Un contrato de compraventa de bienes raíces debe constar por escrito y estar firmado por la parte a quien se le exige.',
                    ],
                ],
            ],
            [
                'key' => 'contracts-minor',
                'state_code' => null,
                'topic' => QuizTopic::Contracts,
                'correct_option' => 1,
                'source' => 'Licensing exam content outline · Contracts: capacity',
                'content' => [
                    'en' => [
                        'question' => 'A 17-year-old signs a contract to buy land. The contract is:',
                        'options' => ['Void', 'Voidable by the minor', 'Valid and binding on both sides', 'Illegal'],
                        'explanation' => 'A minor lacks full capacity, so the contract is voidable: the minor may cancel it, the adult may not.',
                    ],
                    'es' => [
                        'question' => 'Una persona de 17 años firma un contrato para comprar un terreno. El contrato es:',
                        'options' => ['Nulo', 'Anulable por el menor', 'Válido y obligatorio para ambas partes', 'Ilegal'],
                        'explanation' => 'Un menor no tiene plena capacidad, así que el contrato es anulable: el menor puede cancelarlo; el adulto, no.',
                    ],
                ],
            ],
            [
                'key' => 'texas-sponsorship',
                'state_code' => 'TX',
                'topic' => QuizTopic::StateRules,
                'correct_option' => 0,
                'source' => 'Texas Occupations Code ch. 1101 (Real Estate License Act): sales agent sponsorship',
                'content' => [
                    'en' => [
                        'question' => 'In Texas, a licensed sales agent may act for others in a real estate transaction only when they are:',
                        'options' => ['Sponsored by a licensed Texas broker', 'Three years into their career', 'Approved by the buyer in writing', 'Members of a REALTOR® association'],
                        'explanation' => 'A Texas sales agent must be sponsored by, and act for, a licensed broker.',
                    ],
                    'es' => [
                        'question' => 'En Texas, un agente de ventas con licencia puede actuar en nombre de otros en una transacción solo cuando:',
                        'options' => ['Lo patrocina un bróker con licencia en Texas', 'Tiene tres años de experiencia', 'El comprador lo aprueba por escrito', 'Es miembro de una asociación de REALTORS®'],
                        'explanation' => 'En Texas, un agente de ventas debe estar patrocinado por un bróker con licencia y actuar en su nombre.',
                    ],
                ],
            ],
            [
                'key' => 'texas-intermediary',
                'state_code' => 'TX',
                'topic' => QuizTopic::Agency,
                'correct_option' => 2,
                'source' => 'Texas Occupations Code §1101.559 (broker acting as intermediary)',
                'content' => [
                    'en' => [
                        'question' => 'A Texas broker wants to represent both the buyer and the seller in one sale. This is allowed only if:',
                        'options' => ['The broker keeps it private', 'The price is under $500,000', 'Both parties consent in writing (intermediary)', 'The seller pays the whole commission'],
                        'explanation' => 'Texas allows a broker to act as an intermediary only with the written consent of both parties.',
                    ],
                    'es' => [
                        'question' => 'Un bróker de Texas quiere representar al comprador y al vendedor en la misma venta. Solo se permite si:',
                        'options' => ['El bróker lo mantiene en privado', 'El precio es menor de $500,000', 'Ambas partes dan su consentimiento por escrito (intermediario)', 'El vendedor paga toda la comisión'],
                        'explanation' => 'Texas permite que un bróker actúe como intermediario solo con el consentimiento por escrito de ambas partes.',
                    ],
                ],
            ],
        ];
    }
}
