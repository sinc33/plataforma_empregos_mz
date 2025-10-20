import React, { useState } from 'react';
import { Container, Paper, Box, Typography, Grid, Card, CardContent, Avatar, Chip, Button, Tabs, Tab } from '@mui/material';
import { Helmet } from 'react-helmet-async';

function CompanyCandidates() {
  const [tab, setTab] = useState(0);

  // TODO: substituir por dados da API agrupados por vaga
  const byJob = [
    {
      jobId: 1,
      title: 'Desenvolvedor Full Stack',
      candidates: [
        { id: 101, name: 'Ana Silva', status: 'Em análise', skills: ['React', 'Node'] },
        { id: 102, name: 'Carlos M.', status: 'Entrevista', skills: ['React', 'SQL'] },
      ]
    },
    {
      jobId: 2,
      title: 'UI/UX Designer',
      candidates: [
        { id: 201, name: 'Beatriz J.', status: 'Submetida', skills: ['Figma', 'UX'] },
      ]
    }
  ];

  const statuses = ['Submetida', 'Em análise', 'Entrevista', 'Rejeitada', 'Contratada'];

  return (
    <>
      <Helmet>
        <title>Candidatos por Vaga</title>
      </Helmet>
      <Container maxWidth="lg" sx={{ py: 4 }}>
        <Paper sx={{ p: 3 }}>
          <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <Typography variant="h5" fontWeight="bold">Candidatos</Typography>
            <Tabs value={tab} onChange={(_, v) => setTab(v)}>
              <Tab label="Por Vaga" />
              <Tab label="Todos" />
            </Tabs>
          </Box>

          <Grid container spacing={2} sx={{ mt: 1 }}>
            {byJob.map((group) => (
              <Grid item xs={12} md={6} key={group.jobId}>
                <Card>
                  <CardContent>
                    <Typography variant="subtitle1" fontWeight="bold" gutterBottom>
                      {group.title}
                    </Typography>

                    {group.candidates.map((c) => (
                      <Box key={c.id} sx={{ display: 'flex', alignItems: 'center', p: 1.5, borderRadius: 1, border: '1px solid', borderColor: 'divider', mb: 1 }}>
                        <Avatar sx={{ mr: 2 }}>{c.name.charAt(0)}</Avatar>
                        <Box sx={{ flexGrow: 1 }}>
                          <Typography fontWeight="600">{c.name}</Typography>
                          <Box sx={{ display: 'flex', gap: 1, mt: 0.5, flexWrap: 'wrap' }}>
                            {c.skills.map((s) => <Chip key={s} label={s} size="small" />)}
                          </Box>
                        </Box>
                        <Chip label={c.status} color={c.status === 'Entrevista' ? 'warning' : c.status === 'Contratada' ? 'success' : c.status === 'Rejeitada' ? 'error' : 'default'} sx={{ mr: 1 }} />
                        <Button variant="outlined" size="small">Detalhes</Button>
                      </Box>
                    ))}
                  </CardContent>
                </Card>
              </Grid>
            ))}
          </Grid>
        </Paper>
      </Container>
    </>
  );
}

export default CompanyCandidates;
