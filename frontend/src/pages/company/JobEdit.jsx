import React, { useState } from 'react';
import { Container, Paper, Box, Typography, TextField, Button, Chip, Switch, FormControlLabel } from '@mui/material';
import { Helmet } from 'react-helmet-async';
import { useParams } from 'react-router-dom';

function CompanyJobEdit() {
  const { id } = useParams();

  // TODO: carregar dados via API
  const [data, setData] = useState({
    title: 'Desenvolvedor Full Stack',
    location: 'Maputo',
    salary: '45.000 - 65.000 MZN',
    description: 'Funções: desenvolvimento frontend e backend, revisão de código, testes.',
    skills: ['React', 'Node', 'SQL'],
    remote: true
  });

  const save = async () => {
    // TODO: enviar para API
    console.log('Salvar edição', id, data);
  };

  return (
    <>
      <Helmet>
        <title>Editar Vaga #{id}</title>
      </Helmet>
      <Container maxWidth="md" sx={{ py: 4 }}>
        <Paper sx={{ p: 3 }}>
          <Typography variant="h5" fontWeight="bold" gutterBottom>
            Editar Vaga
          </Typography>

          <TextField fullWidth label="Título" margin="normal" value={data.title} onChange={(e) => setData({ ...data, title: e.target.value })} />
          <TextField fullWidth label="Localização" margin="normal" value={data.location} onChange={(e) => setData({ ...data, location: e.target.value })} />
          <TextField fullWidth label="Faixa Salarial" margin="normal" value={data.salary} onChange={(e) => setData({ ...data, salary: e.target.value })} />
          <TextField fullWidth label="Descrição" margin="normal" multiline rows={4} value={data.description} onChange={(e) => setData({ ...data, description: e.target.value })} />

          <Box sx={{ mt: 2 }}>
            <Typography variant="subtitle1" gutterBottom>Competências</Typography>
            {data.skills.map((s, i) => (
              <Chip key={i} label={s} onDelete={() => setData({ ...data, skills: data.skills.filter((_, idx) => idx !== i) })} sx={{ mr: 1, mb: 1 }} />
            ))}
          </Box>

          <FormControlLabel control={<Switch checked={data.remote} onChange={(e) => setData({ ...data, remote: e.target.checked })} />} label="Vaga Remota" sx={{ mt: 2 }} />

          <Box sx={{ display: 'flex', gap: 2, mt: 3 }}>
            <Button variant="contained" onClick={save}>Guardar</Button>
            <Button variant="outlined">Cancelar</Button>
          </Box>
        </Paper>
      </Container>
    </>
  );
}

export default CompanyJobEdit;
