/**
 * Mapeamento padrão de emoji por espécie animal.
 *
 * Centraliza o que estava duplicado em 4+ wizards. Regra: cada espécie
 * usa o EMOJI ESPECÍFICO (cabra ≠ ovelha, cão ≠ gato), nunca um genérico
 * quando é possível ser preciso. Respeita a regra de produto: ícone
 * precisa representar corretamente a entidade.
 *
 * Fallback `🐾` só é usado quando a espécie não bate com nenhum padrão
 * conhecido — idealmente deveria ser zero-frequência em produção.
 */
export function emojiEspecie(nome) {
    const n = (nome || '').toLowerCase();

    // Ordem importante: específico antes de genérico (ex.: búfalo antes de bovino)
    if (n.includes('búfalo') || n.includes('bufalo')) return '🐃';
    if (n.includes('bovino') || n.includes('gado') || n.includes('vaca') || n.includes('boi')) return '🐄';
    if (n.includes('suíno') || n.includes('suino') || n.includes('porco')) return '🐖';
    // Caprino = cabra (🐐); Ovino = ovelha (🐑) — SÃO ESPÉCIES DIFERENTES.
    if (n.includes('caprino') || n.includes('cabra') || n.includes('bode')) return '🐐';
    if (n.includes('ovino') || n.includes('ovelha') || n.includes('cordeiro') || n.includes('carneiro')) return '🐑';
    if (n.includes('galinha') || n.includes('frango') || n.includes('poedeira') || n.includes('ave')) return '🐔';
    if (n.includes('peru')) return '🦃';
    if (n.includes('pato') || n.includes('marreco')) return '🦆';
    if (n.includes('equino') || n.includes('cavalo') || n.includes('égua') || n.includes('egua')) return '🐎';
    if (n.includes('asinino') || n.includes('jumento') || n.includes('burro') || n.includes('mula')) return '🦓';
    if (n.includes('peixe') || n.includes('piscic') || n.includes('tilápia') || n.includes('tilapia') || n.includes('tambaqui') || n.includes('pacu')) return '🐟';
    if (n.includes('camarão') || n.includes('camarao')) return '🦐';
    if (n.includes('abelha') || n.includes('apis') || n.includes('api')) return '🐝';
    if (n.includes('cão') || n.includes('cao') || n.includes('cachorro')) return '🐕';
    if (n.includes('gato') || n.includes('felino')) return '🐈';
    if (n.includes('coelho') || n.includes('lebre')) return '🐇';

    return '🐾';
}
